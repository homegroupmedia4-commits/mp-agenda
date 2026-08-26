/**
 * MP Agenda — Wizard de réservation front-end (vanilla JS).
 */
( function () {
	'use strict';

	var cfg = window.mpAgendaPublic || {};
	var dayMap = [ 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' ];

	var STEP_META = {
		'1': { icon: '🏬', title: 'Showroom', desc: 'Choisissez le showroom le plus proche de chez vous.' },
		'2': { icon: '🛠️', title: 'Services', desc: 'Sélectionnez le service qui correspond à votre projet.' },
		'3': { icon: '🧑‍💼', title: 'Commercial', desc: 'Choisissez votre commercial ou laissez-nous choisir pour vous.' },
		'4': { icon: '📅', title: 'Date & heure', desc: 'Choisissez le créneau qui vous convient le mieux.' },
		'5': { icon: '📝', title: 'Vos informations', desc: 'Renseignez vos coordonnées pour finaliser la demande.' },
		'6': { icon: '✅', title: 'Confirmation', desc: 'Vérifiez le récapitulatif avant de confirmer votre rendez-vous.' },
		success: { icon: '🎉', title: 'C\'est confirmé !', desc: 'Merci, à très bientôt.' },
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'mp-agenda-booking-root' );
		if ( ! root ) {
			return;
		}
		new MPAgendaBooking( root ).init();
	} );

	function MPAgendaBooking( root ) {
		this.root = root;
		this.form = root.querySelector( '#mp-agenda-booking-form' );

		this.showrooms = [];
		this.selectedShowroomId = 0;

		this.services = [];
		this.selectedServiceId = 0;
		this.selectedServiceDuration = 60;

		this.technicians = [];
		this.selectedTechnicianId = 0; // 0 = "peu importe".
		this.selectedTechnicianForSlot = null;

		this.currentMonth = new Date();
		this.currentMonth.setDate( 1 );
		this.selectedDate = null;
		this.selectedTime = null;
	}

	MPAgendaBooking.prototype.init = function () {
		this.bindNavigation();
		this.bindCalendarNav();
		this.bindSubmit();
		this.loadShowrooms();
		this.loadServices();
		this.loadTechnicians();
	};

	/* --------------------------------------------------------------------
	 * Requêtes API (transport admin-ajax.php — compatible hébergeurs
	 * qui bloquent /wp-json/)
	 * ------------------------------------------------------------------ */

	function apiRequest( path, method, body ) {
		var formData = new FormData();
		formData.append( 'action', 'mp_agenda_api' );
		formData.append( 'nonce', cfg.nonce );
		formData.append( 'route', path );
		formData.append( 'method', method || 'GET' );
		if ( body ) {
			formData.append( 'data', JSON.stringify( body ) );
		}

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} ).then( function ( response ) {
			return response.json().then( function ( json ) {
				if ( ! json.success ) {
					var errData = json.data || {};
					var err = new Error( errData.message || cfg.i18n.genericError );
					err.code = errData.code;
					throw err;
				}
				return json.data;
			} );
		} );
	}

	MPAgendaBooking.prototype.apiGet = function ( path ) {
		return apiRequest( path, 'GET' );
	};

	MPAgendaBooking.prototype.apiPost = function ( path, body ) {
		return apiRequest( path, 'POST', body );
	};

	/* --------------------------------------------------------------------
	 * Navigation entre étapes
	 * ------------------------------------------------------------------ */

	MPAgendaBooking.prototype.bindNavigation = function () {
		var self = this;

		this.root.querySelectorAll( '[data-next]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				self.goToStep( btn.dataset.next );
			} );
		} );

		this.root.querySelectorAll( '[data-prev]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				self.goToStep( btn.dataset.prev );
			} );
		} );

		var toRecapBtn = this.root.querySelector( '#mp-agenda-to-recap' );
		if ( toRecapBtn ) {
			toRecapBtn.addEventListener( 'click', function () {
				if ( self.validateInfoStep() ) {
					self.renderRecap();
					self.goToStep( '6' );
				}
			} );
		}
	};

	MPAgendaBooking.prototype.goToStep = function ( step ) {
		this.root.querySelectorAll( '.mp-agenda-panel' ).forEach( function ( panel ) {
			panel.classList.toggle( 'is-active', panel.dataset.stepPanel === step );
		} );

		this.root.querySelectorAll( '.mp-agenda-step-indicator' ).forEach( function ( indicator ) {
			var indicatorStep = parseInt( indicator.dataset.step, 10 );
			var target = parseInt( step, 10 );
			indicator.classList.toggle( 'active', indicatorStep === target );
			indicator.classList.toggle( 'done', indicatorStep < target );
		} );

		this.updateSidebar( step );
		this.root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	};

	MPAgendaBooking.prototype.updateSidebar = function ( step ) {
		var meta = STEP_META[ step ];
		if ( ! meta ) {
			return;
		}
		var iconEl = document.getElementById( 'mp-agenda-sidebar-icon' );
		var titleEl = document.getElementById( 'mp-agenda-sidebar-title' );
		var descEl = document.getElementById( 'mp-agenda-sidebar-desc' );
		if ( iconEl ) {
			iconEl.textContent = meta.icon;
		}
		if ( titleEl ) {
			titleEl.textContent = meta.title;
		}
		if ( descEl ) {
			descEl.textContent = meta.desc;
		}
	};

	/* --------------------------------------------------------------------
	 * Fiche récapitulative (sidebar droite)
	 * ------------------------------------------------------------------ */

	MPAgendaBooking.prototype.setFicheRow = function ( key, value ) {
		var row = this.root.querySelector( '[data-fiche-row="' + key + '"]' );
		var valueEl = document.getElementById( 'mp-agenda-fiche-' + key );
		if ( ! row || ! valueEl ) {
			return;
		}
		if ( value ) {
			valueEl.textContent = value;
			row.hidden = false;
		} else {
			row.hidden = true;
		}
	};

	/* --------------------------------------------------------------------
	 * Étape 1 : Showroom
	 * ------------------------------------------------------------------ */

	MPAgendaBooking.prototype.loadShowrooms = function () {
		var self = this;
		this.apiGet( '/showrooms' )
			.then( function ( data ) {
				self.showrooms = data.items || [];
				self.renderShowroomCards();
			} )
			.catch( function () {
				document.getElementById( 'mp-agenda-showroom-cards' ).innerHTML = '<div class="mp-agenda-empty">' + cfg.i18n.genericError + '</div>';
			} );
	};

	MPAgendaBooking.prototype.renderShowroomCards = function () {
		var self = this;
		var container = document.getElementById( 'mp-agenda-showroom-cards' );
		container.innerHTML = '';

		if ( ! this.showrooms.length ) {
			container.innerHTML = '<div class="mp-agenda-empty">' + cfg.i18n.noShowrooms + '</div>';
			return;
		}

		this.showrooms.forEach( function ( showroom ) {
			var card = document.createElement( 'div' );
			card.className = 'mp-agenda-showroom-card';
			card.dataset.showroomId = showroom.id;

			var media = showroom.photo_url
				? '<img src="' + showroom.photo_url + '" alt="" />'
				: '<div class="mp-agenda-showroom-photo-placeholder">' + escapeHtml( showroom.name.charAt( 0 ) ) + '</div>';

			card.innerHTML = media + '<strong>' + escapeHtml( showroom.name ) + '</strong>' + ( showroom.address ? '<span>' + escapeHtml( showroom.address ) + '</span>' : '' );

			card.addEventListener( 'click', function () {
				self.selectShowroom( showroom.id, card );
			} );

			container.appendChild( card );
		} );
	};

	MPAgendaBooking.prototype.selectShowroom = function ( id, cardEl ) {
		this.selectedShowroomId = parseInt( id, 10 );
		this.root.querySelectorAll( '.mp-agenda-showroom-card' ).forEach( function ( c ) {
			c.classList.remove( 'is-selected' );
		} );
		cardEl.classList.add( 'is-selected' );

		var showroom = this.showrooms.filter( function ( s ) {
			return parseInt( s.id, 10 ) === this.selectedShowroomId;
		}, this )[ 0 ];

		this.setFicheRow( 'showroom', showroom ? showroom.name : '' );
		this.updateSidebarPhone( showroom );

		// Le showroom détermine les commerciaux disponibles : on réinitialise la suite.
		this.selectedTechnicianId = 0;
		this.selectedDate = null;
		this.selectedTime = null;
		this.root.querySelectorAll( '.mp-agenda-technician-card' ).forEach( function ( c ) {
			c.classList.remove( 'is-selected' );
		} );
		this.setFicheRow( 'technician', '' );
		this.setFicheRow( 'datetime', '' );

		this.loadTechnicians();
	};

	MPAgendaBooking.prototype.updateSidebarPhone = function ( showroom ) {
		var wrap = document.getElementById( 'mp-agenda-sidebar-phone' );
		var link = document.getElementById( 'mp-agenda-sidebar-phone-link' );
		if ( ! wrap || ! link ) {
			return;
		}
		if ( showroom && showroom.phone ) {
			link.href = 'tel:' + showroom.phone.replace( /\s+/g, '' );
			link.textContent = showroom.phone;
			wrap.hidden = false;
		} else {
			wrap.hidden = true;
		}
	};

	/* --------------------------------------------------------------------
	 * Étape 2 : Services
	 * ------------------------------------------------------------------ */

	MPAgendaBooking.prototype.loadServices = function () {
		var self = this;
		this.apiGet( '/services' )
			.then( function ( data ) {
				self.services = data.items || [];
				self.renderServiceCards();
			} )
			.catch( function () {
				document.getElementById( 'mp-agenda-service-cards' ).innerHTML = '<div class="mp-agenda-empty">' + cfg.i18n.genericError + '</div>';
			} );
	};

	MPAgendaBooking.prototype.renderServiceCards = function () {
		var self = this;
		var container = document.getElementById( 'mp-agenda-service-cards' );
		container.innerHTML = '';

		if ( ! this.services.length ) {
			container.innerHTML = '<div class="mp-agenda-empty">' + cfg.i18n.noServices + '</div>';
			return;
		}

		this.services.forEach( function ( service ) {
			var card = document.createElement( 'div' );
			card.className = 'mp-agenda-service-card';
			card.dataset.serviceId = service.id;

			card.innerHTML =
				'<div class="mp-agenda-service-icon">' + ( service.icon || '🛠️' ) + '</div>' +
				'<strong>' + escapeHtml( service.name ) + '</strong>' +
				( service.description ? '<span>' + escapeHtml( service.description ) + '</span>' : '' );

			card.addEventListener( 'click', function () {
				self.selectService( service, card );
			} );

			container.appendChild( card );
		} );
	};

	MPAgendaBooking.prototype.selectService = function ( service, cardEl ) {
		this.selectedServiceId = parseInt( service.id, 10 );
		this.selectedServiceDuration = parseInt( service.duration, 10 ) || 60;

		this.root.querySelectorAll( '.mp-agenda-service-card' ).forEach( function ( c ) {
			c.classList.remove( 'is-selected' );
		} );
		cardEl.classList.add( 'is-selected' );

		this.setFicheRow( 'service', service.name );

		// La durée du créneau dépend du service : on réinitialise la date/heure.
		this.selectedDate = null;
		this.selectedTime = null;
		this.setFicheRow( 'datetime', '' );
	};

	/* --------------------------------------------------------------------
	 * Étape 3 : Commercial
	 * ------------------------------------------------------------------ */

	MPAgendaBooking.prototype.loadTechnicians = function () {
		var self = this;
		var container = document.getElementById( 'mp-agenda-technician-cards' );
		container.innerHTML = '<div class="mp-agenda-loading">' + cfg.i18n.loading + '</div>';

		var path = '/technicians' + ( this.selectedShowroomId ? '?showroom_id=' + this.selectedShowroomId : '' );

		this.apiGet( path )
			.then( function ( data ) {
				self.technicians = data.items || [];
				self.renderTechnicianCards();
			} )
			.catch( function () {
				container.innerHTML = '<div class="mp-agenda-empty">' + cfg.i18n.genericError + '</div>';
			} );
	};

	MPAgendaBooking.prototype.renderTechnicianCards = function () {
		var self = this;
		var container = document.getElementById( 'mp-agenda-technician-cards' );
		container.innerHTML = '';

		this.technicians.forEach( function ( tech ) {
			var card = document.createElement( 'div' );
			card.className = 'mp-agenda-technician-card';
			card.dataset.technicianId = tech.id;

			var media = tech.photo_url
				? '<img src="' + tech.photo_url + '" alt="" />'
				: '<div class="mp-agenda-technician-avatar-placeholder">' + escapeHtml( tech.name.charAt( 0 ) ) + '</div>';

			card.innerHTML = media + '<strong>' + escapeHtml( tech.name ) + '</strong>' + ( tech.zone ? '<span>' + escapeHtml( tech.zone ) + '</span>' : '' );

			card.addEventListener( 'click', function () {
				self.selectTechnician( tech.id, tech.name, card );
			} );

			container.appendChild( card );
		} );

		if ( ! cfg.requireTechnicianChoice ) {
			var anyCard = document.createElement( 'div' );
			anyCard.className = 'mp-agenda-technician-card';
			anyCard.dataset.technicianId = '0';
			anyCard.innerHTML = '<div class="mp-agenda-technician-avatar-placeholder">?</div><strong>' + cfg.i18n.anyTechnician + '</strong>';
			anyCard.addEventListener( 'click', function () {
				self.selectTechnician( 0, cfg.i18n.anyTechnician, anyCard );
			} );
			container.appendChild( anyCard );
		}
	};

	MPAgendaBooking.prototype.selectTechnician = function ( id, name, cardEl ) {
		this.selectedTechnicianId = parseInt( id, 10 );
		this.root.querySelectorAll( '.mp-agenda-technician-card' ).forEach( function ( c ) {
			c.classList.remove( 'is-selected' );
		} );
		cardEl.classList.add( 'is-selected' );

		this.setFicheRow( 'technician', name );

		// Réinitialise la sélection de date/heure si on change de commercial.
		this.selectedDate = null;
		this.selectedTime = null;
		this.setFicheRow( 'datetime', '' );
	};

	/* --------------------------------------------------------------------
	 * Étape 4 : Mini-calendrier & créneaux
	 * ------------------------------------------------------------------ */

	MPAgendaBooking.prototype.bindCalendarNav = function () {
		var self = this;
		document.getElementById( 'mp-agenda-cal-prev' ).addEventListener( 'click', function () {
			self.currentMonth.setMonth( self.currentMonth.getMonth() - 1 );
			self.renderCalendar();
		} );
		document.getElementById( 'mp-agenda-cal-next' ).addEventListener( 'click', function () {
			self.currentMonth.setMonth( self.currentMonth.getMonth() + 1 );
			self.renderCalendar();
		} );

		// Rend le calendrier au premier passage à l'étape 4 (Date & heure).
		this.root.querySelectorAll( '[data-next="4"]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				self.renderCalendar();
			} );
		} );
	};

	MPAgendaBooking.prototype.getTechniciansForAvailability = function () {
		if ( this.selectedTechnicianId ) {
			return this.technicians.filter( function ( t ) {
				return parseInt( t.id, 10 ) === this.selectedTechnicianId;
			}, this );
		}
		return this.technicians;
	};

	MPAgendaBooking.prototype.isDayWorked = function ( date ) {
		var techs = this.getTechniciansForAvailability();
		var dayKey = dayMap[ date.getDay() ];
		return techs.some( function ( tech ) {
			var config = tech.working_hours && tech.working_hours[ dayKey ];
			return config && config.active;
		} );
	};

	MPAgendaBooking.prototype.renderCalendar = function () {
		var self = this;
		var year = this.currentMonth.getFullYear();
		var month = this.currentMonth.getMonth();

		document.getElementById( 'mp-agenda-cal-label' ).textContent = cfg.i18n.months[ month ] + ' ' + year;

		var weekdaysEl = document.getElementById( 'mp-agenda-cal-weekdays' );
		weekdaysEl.innerHTML = '';
		[ 1, 2, 3, 4, 5, 6, 0 ].forEach( function ( dayIndex ) {
			var span = document.createElement( 'span' );
			span.textContent = cfg.i18n.days[ dayIndex ];
			weekdaysEl.appendChild( span );
		} );

		var daysEl = document.getElementById( 'mp-agenda-cal-days' );
		daysEl.innerHTML = '';

		var firstOfMonth = new Date( year, month, 1 );
		var startOffset = ( firstOfMonth.getDay() + 6 ) % 7; // lundi = 0
		var daysInMonth = new Date( year, month + 1, 0 ).getDate();

		var today = new Date();
		today.setHours( 0, 0, 0, 0 );

		for ( var i = 0; i < startOffset; i++ ) {
			var empty = document.createElement( 'div' );
			empty.className = 'mp-agenda-cal-day is-empty';
			daysEl.appendChild( empty );
		}

		for ( var day = 1; day <= daysInMonth; day++ ) {
			var date = new Date( year, month, day );
			var cell = document.createElement( 'div' );
			cell.className = 'mp-agenda-cal-day';
			cell.textContent = day;

			var isPast = date < today;
			var isWorked = self.isDayWorked( date );

			if ( isPast || ! isWorked ) {
				cell.classList.add( 'is-disabled' );
			} else {
				cell.addEventListener( 'click', function ( clickedDate, clickedCell ) {
					return function () {
						self.selectDate( clickedDate, clickedCell );
					};
				}( date, cell ) );
			}

			if ( date.getTime() === today.getTime() ) {
				cell.classList.add( 'is-today' );
			}
			if ( self.selectedDate && formatDate( date ) === self.selectedDate ) {
				cell.classList.add( 'is-selected' );
			}

			daysEl.appendChild( cell );
		}
	};

	MPAgendaBooking.prototype.selectDate = function ( date, cellEl ) {
		this.selectedDate = formatDate( date );
		this.selectedTime = null;

		this.root.querySelectorAll( '.mp-agenda-cal-day' ).forEach( function ( c ) {
			c.classList.remove( 'is-selected' );
		} );
		cellEl.classList.add( 'is-selected' );

		document.getElementById( 'mp-agenda-slots-title' ).textContent = date.toLocaleDateString( 'fr-FR', { weekday: 'long', day: 'numeric', month: 'long' } );

		this.loadSlots();
	};

	MPAgendaBooking.prototype.loadSlots = function () {
		var self = this;
		var slotsEl = document.getElementById( 'mp-agenda-slots' );
		slotsEl.innerHTML = '<div class="mp-agenda-loading">' + cfg.i18n.loading + '</div>';

		var techs = this.getTechniciansForAvailability();
		var duration = this.selectedServiceDuration || 60;

		Promise.all(
			techs.map( function ( tech ) {
				return self
					.apiGet( '/available-slots?technician_id=' + tech.id + '&date=' + self.selectedDate + '&duration=' + duration )
					.then( function ( data ) {
						return { technician: tech, slots: data.slots || [] };
					} )
					.catch( function () {
						return { technician: tech, slots: [] };
					} );
			} )
		).then( function ( results ) {
			self.renderSlots( results );
		} );
	};

	MPAgendaBooking.prototype.renderSlots = function ( results ) {
		var self = this;
		var slotsEl = document.getElementById( 'mp-agenda-slots' );
		slotsEl.innerHTML = '';

		// Fusionne les créneaux de tous les commerciaux interrogés (dédoublonnés, triés).
		var slotMap = {};
		results.forEach( function ( result ) {
			result.slots.forEach( function ( time ) {
				if ( ! slotMap[ time ] ) {
					slotMap[ time ] = result.technician.id;
				}
			} );
		} );

		var times = Object.keys( slotMap ).sort();

		if ( ! times.length ) {
			slotsEl.innerHTML = '<div class="mp-agenda-empty">' + cfg.i18n.noSlots + '</div>';
			return;
		}

		times.forEach( function ( time ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'mp-agenda-slot-btn';
			btn.textContent = time;
			btn.addEventListener( 'click', function () {
				self.selectTime( time, slotMap[ time ], btn );
			} );
			slotsEl.appendChild( btn );
		} );
	};

	MPAgendaBooking.prototype.selectTime = function ( time, technicianId, btnEl ) {
		this.selectedTime = time;
		this.selectedTechnicianForSlot = technicianId;

		this.root.querySelectorAll( '.mp-agenda-slot-btn' ).forEach( function ( b ) {
			b.classList.remove( 'is-selected' );
		} );
		btnEl.classList.add( 'is-selected' );

		if ( this.selectedDate ) {
			var dateObj = new Date( this.selectedDate + 'T00:00:00' );
			var label = dateObj.toLocaleDateString( 'fr-FR', { day: 'numeric', month: 'long' } ) + ' à ' + time;
			this.setFicheRow( 'datetime', label );
		}
	};

	/* --------------------------------------------------------------------
	 * Étape 5 : validation
	 * ------------------------------------------------------------------ */

	MPAgendaBooking.prototype.validateInfoStep = function () {
		var errorEl = document.getElementById( 'mp-agenda-step5-error' );
		errorEl.hidden = true;

		if ( ! this.selectedDate || ! this.selectedTime ) {
			errorEl.textContent = cfg.i18n.selectSlot;
			errorEl.hidden = false;
			this.goToStep( '4' );
			return false;
		}

		var name = document.getElementById( 'mp-agenda-name' ).value.trim();
		var phone = document.getElementById( 'mp-agenda-phone' ).value.trim();
		var address = document.getElementById( 'mp-agenda-address' ).value.trim();
		var gdpr = document.getElementById( 'mp-agenda-gdpr' ).checked;

		if ( ! name || ! phone || ! address ) {
			errorEl.textContent = cfg.i18n.requiredFields;
			errorEl.hidden = false;
			return false;
		}

		if ( ! gdpr ) {
			errorEl.textContent = cfg.i18n.gdprRequired;
			errorEl.hidden = false;
			return false;
		}

		return true;
	};

	/* --------------------------------------------------------------------
	 * Étape 6 : récapitulatif & envoi
	 * ------------------------------------------------------------------ */

	MPAgendaBooking.prototype.renderRecap = function () {
		var techId = this.selectedTechnicianForSlot || this.selectedTechnicianId;
		var tech = this.technicians.filter( function ( t ) {
			return parseInt( t.id, 10 ) === parseInt( techId, 10 );
		} )[ 0 ];

		var showroom = this.showrooms.filter( function ( s ) {
			return parseInt( s.id, 10 ) === parseInt( this.selectedShowroomId, 10 );
		}, this )[ 0 ];

		var service = this.services.filter( function ( s ) {
			return parseInt( s.id, 10 ) === parseInt( this.selectedServiceId, 10 );
		}, this )[ 0 ];

		var date = new Date( this.selectedDate + 'T00:00:00' );
		var dateLabel = date.toLocaleDateString( 'fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' } );

		var name = document.getElementById( 'mp-agenda-name' ).value;
		var phone = document.getElementById( 'mp-agenda-phone' ).value;
		var address = document.getElementById( 'mp-agenda-address' ).value;

		document.getElementById( 'mp-agenda-recap' ).innerHTML =
			'<dl>' +
			( showroom ? '<dt>Showroom</dt><dd>' + escapeHtml( showroom.name ) + '</dd>' : '' ) +
			( service ? '<dt>Service</dt><dd>' + escapeHtml( service.name ) + '</dd>' : '' ) +
			'<dt>Commercial</dt><dd>' + escapeHtml( tech ? tech.name : '' ) + '</dd>' +
			'<dt>Date et heure</dt><dd>' + escapeHtml( dateLabel ) + ' à ' + escapeHtml( this.selectedTime ) + '</dd>' +
			'<dt>Client</dt><dd>' + escapeHtml( name ) + ' — ' + escapeHtml( phone ) + '</dd>' +
			'<dt>Adresse</dt><dd>' + escapeHtml( address ) + '</dd>' +
			'</dl>';
	};

	MPAgendaBooking.prototype.bindSubmit = function () {
		var self = this;
		this.form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			self.submitBooking();
		} );
	};

	MPAgendaBooking.prototype.submitBooking = function () {
		var self = this;
		var errorEl = document.getElementById( 'mp-agenda-step6-error' );
		errorEl.hidden = true;

		var submitBtn = document.getElementById( 'mp-agenda-submit' );
		submitBtn.disabled = true;

		var techId = this.selectedTechnicianForSlot || this.selectedTechnicianId;

		var payload = {
			technician_id: techId,
			service_id: this.selectedServiceId || null,
			duration: this.selectedServiceDuration || 60,
			date: this.selectedDate,
			time: this.selectedTime,
			client_name: document.getElementById( 'mp-agenda-name' ).value,
			client_phone: document.getElementById( 'mp-agenda-phone' ).value,
			client_email: document.getElementById( 'mp-agenda-email' ).value,
			client_address: document.getElementById( 'mp-agenda-address' ).value,
			notes: document.getElementById( 'mp-agenda-notes' ).value,
			gdpr_accepted: document.getElementById( 'mp-agenda-gdpr' ).checked,
			booking_nonce: cfg.bookingNonce,
		};

		this.apiPost( '/book', payload )
			.then( function ( data ) {
				document.getElementById( 'mp-agenda-success-message' ).textContent =
					'Merci ' + payload.client_name + ', votre rendez-vous est enregistré. Un email de confirmation vous sera envoyé si vous avez renseigné votre adresse.';
				self.goToStep( 'success' );
			} )
			.catch( function ( err ) {
				errorEl.textContent = err.message || cfg.i18n.genericError;
				errorEl.hidden = false;

				if ( 'mp_agenda_slot_taken' === err.code ) {
					self.goToStep( '4' );
					self.loadSlots();
				}
			} )
			.finally( function () {
				submitBtn.disabled = false;
			} );
	};

	/* --------------------------------------------------------------------
	 * Utilitaires
	 * ------------------------------------------------------------------ */

	function formatDate( date ) {
		var pad = function ( n ) {
			return n < 10 ? '0' + n : '' + n;
		};
		return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() );
	}

	function escapeHtml( str ) {
		if ( ! str ) {
			return '';
		}
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}
} )();
