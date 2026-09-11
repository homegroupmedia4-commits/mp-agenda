/**
 * MP Agenda — Scripts de l'interface d'administration (vanilla JS).
 */
( function () {
	'use strict';

	var cfg = window.mpAgendaAdmin || {};

	/* ---------------------------------------------------------------------
	 * Utilitaires API (transport admin-ajax.php — compatible hébergeurs
	 * qui bloquent /wp-json/)
	 * ------------------------------------------------------------------- */

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
					var err = new Error( errData.message || 'Erreur API' );
					err.data = errData;
					err.code = errData.code;
					throw err;
				}
				return json.data;
			} );
		} );
	}

	function pad( n ) {
		return n < 10 ? '0' + n : '' + n;
	}

	function formatDate( date ) {
		return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() );
	}

	function startOfWeek( date ) {
		var d = new Date( date );
		var day = d.getDay();
		var diff = ( day === 0 ? -6 : 1 ) - day; // lundi = début de semaine
		d.setDate( d.getDate() + diff );
		return d;
	}

	/**
	 * Affiche une notification légère (toast) en bas à droite de l'écran, utilisée
	 * pour signaler l'échec d'une action optimiste (RDV créé/modifié/supprimé
	 * localement puis annulé suite à une erreur serveur).
	 */
	function showNotification( message, isError ) {
		var el = document.createElement( 'div' );
		el.className = 'mp-agenda-toast' + ( isError ? ' mp-agenda-toast-error' : '' );
		el.textContent = message;
		el.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:100000;' +
			'max-width:320px;padding:12px 18px;border-radius:4px;color:#fff;' +
			'font-size:13px;line-height:1.4;box-shadow:0 2px 8px rgba(0,0,0,.2);' +
			'background:' + ( isError ? '#d63638' : '#1d2327' ) + ';';
		document.body.appendChild( el );
		setTimeout( function () {
			el.remove();
		}, 5000 );
	}

	/* ---------------------------------------------------------------------
	 * Media picker (page Commerciaux)
	 * ------------------------------------------------------------------- */

	function initMediaPicker() {
		var btn = document.getElementById( 'mp-photo-select' );
		if ( ! btn || typeof wp === 'undefined' || ! wp.media ) {
			return;
		}

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( { title: 'Choisir une photo', multiple: false } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				document.getElementById( 'mp-photo-url' ).value = attachment.url;
				var preview = document.getElementById( 'mp-photo-preview' );
				preview.src = attachment.url;
				preview.style.display = '';
			} );
			frame.open();
		} );
	}

	/* ---------------------------------------------------------------------
	 * Types d'intervention (page Réglages)
	 * ------------------------------------------------------------------- */

	function initInterventionTypes() {
		var addBtn = document.getElementById( 'mp-agenda-add-type' );
		var list = document.getElementById( 'mp-agenda-types-list' );
		if ( ! addBtn || ! list ) {
			return;
		}

		addBtn.addEventListener( 'click', function () {
			var row = document.createElement( 'div' );
			row.className = 'mp-agenda-type-row';
			row.innerHTML = '<input type="text" name="intervention_types[]" value="" /><button type="button" class="button mp-agenda-remove-type">&times;</button>';
			list.appendChild( row );
		} );

		list.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'mp-agenda-remove-type' ) ) {
				e.target.closest( '.mp-agenda-type-row' ).remove();
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * Modal de rendez-vous (partagée entre Planning et Liste des RDV)
	 * ------------------------------------------------------------------- */

	var Modal = {
		overlay: null,
		form: null,

		init: function () {
			this.overlay = document.getElementById( 'mp-agenda-modal-overlay' );
			if ( ! this.overlay ) {
				return;
			}
			this.form = document.getElementById( 'mp-agenda-appointment-form' );

			this.populateTimeSelect();

			this.overlay.querySelector( '.mp-agenda-modal-close' ).addEventListener( 'click', this.close.bind( this ) );
			this.overlay.querySelector( '.mp-agenda-modal-cancel' ).addEventListener( 'click', this.close.bind( this ) );
			this.overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === Modal.overlay ) {
					Modal.close();
				}
			} );

			this.form.addEventListener( 'submit', this.handleSubmit.bind( this ) );
			this.overlay.querySelector( '.mp-agenda-delete-btn' ).addEventListener( 'click', this.handleDelete.bind( this ) );
		},

		populateTimeSelect: function () {
			var select = document.getElementById( 'mp-appt-time' );
			select.innerHTML = '';
			for ( var h = 7; h <= 20; h++ ) {
				for ( var m = 0; m < 60; m += 30 ) {
					if ( h === 20 && m > 0 ) {
						continue;
					}
					var value = pad( h ) + ':' + pad( m );
					var opt = document.createElement( 'option' );
					opt.value = value;
					opt.textContent = value;
					select.appendChild( opt );
				}
			}
		},

		openForCreate: function ( technicianId, date, time ) {
			this.form.reset();
			document.getElementById( 'mp-appt-id' ).value = '';
			document.getElementById( 'mp-agenda-modal-title' ).textContent = 'Nouveau rendez-vous';
			this.overlay.querySelector( '.mp-agenda-status-field' ).hidden = true;
			this.overlay.querySelector( '.mp-agenda-delete-btn' ).hidden = true;
			this.hideError();

			if ( technicianId ) {
				document.getElementById( 'mp-appt-technician' ).value = technicianId;
			}
			document.getElementById( 'mp-appt-date' ).value = date || formatDate( new Date() );
			if ( time ) {
				document.getElementById( 'mp-appt-time' ).value = time;
			}

			this.show();
		},

		openForEdit: function ( appointment ) {
			this.form.reset();
			document.getElementById( 'mp-agenda-modal-title' ).textContent = 'Modifier le rendez-vous';
			this.overlay.querySelector( '.mp-agenda-status-field' ).hidden = false;
			this.overlay.querySelector( '.mp-agenda-delete-btn' ).hidden = false;
			this.hideError();

			var start = new Date( appointment.start_datetime.replace( ' ', 'T' ) );

			document.getElementById( 'mp-appt-id' ).value = appointment.id;
			document.getElementById( 'mp-appt-technician' ).value = appointment.technician_id;
			document.getElementById( 'mp-appt-date' ).value = formatDate( start );
			document.getElementById( 'mp-appt-time' ).value = pad( start.getHours() ) + ':' + pad( start.getMinutes() );
			document.getElementById( 'mp-appt-duration' ).value = appointment.duration;
			document.getElementById( 'mp-appt-client-name' ).value = appointment.client_name || '';
			document.getElementById( 'mp-appt-client-phone' ).value = appointment.client_phone || '';
			document.getElementById( 'mp-appt-client-email' ).value = appointment.client_email || '';
			document.getElementById( 'mp-appt-client-address' ).value = appointment.client_address || '';
			document.getElementById( 'mp-appt-service' ).value = appointment.service_id || '';
			document.getElementById( 'mp-appt-surface' ).value = appointment.surface || '';
			document.getElementById( 'mp-appt-notes' ).value = appointment.internal_notes || '';
			document.getElementById( 'mp-appt-status' ).value = appointment.status || 'confirmed';

			var urgencyRadios = this.form.querySelectorAll( 'input[name="urgency"]' );
			urgencyRadios.forEach( function ( radio ) {
				radio.checked = radio.value === ( appointment.urgency || 'normal' );
			} );

			this.show();
		},

		show: function () {
			this.overlay.hidden = false;
		},

		close: function () {
			this.overlay.hidden = true;
		},

		showError: function ( message ) {
			var errorBox = this.overlay.querySelector( '.mp-agenda-modal-error' );
			errorBox.textContent = message;
			errorBox.hidden = false;
		},

		hideError: function () {
			this.overlay.querySelector( '.mp-agenda-modal-error' ).hidden = true;
		},

		handleSubmit: function ( e ) {
			e.preventDefault();
			this.hideError();

			var id = document.getElementById( 'mp-appt-id' ).value;
			var date = document.getElementById( 'mp-appt-date' ).value;
			var time = document.getElementById( 'mp-appt-time' ).value;
			var duration = parseInt( document.getElementById( 'mp-appt-duration' ).value, 10 );
			var startDatetime = date + ' ' + time + ':00';

			var payload = {
				technician_id: parseInt( document.getElementById( 'mp-appt-technician' ).value, 10 ),
				start_datetime: startDatetime,
				duration: duration,
				client_name: document.getElementById( 'mp-appt-client-name' ).value,
				client_phone: document.getElementById( 'mp-appt-client-phone' ).value,
				client_email: document.getElementById( 'mp-appt-client-email' ).value,
				client_address: document.getElementById( 'mp-appt-client-address' ).value,
				service_id: document.getElementById( 'mp-appt-service' ).value || null,
				surface: document.getElementById( 'mp-appt-surface' ).value,
				urgency: this.form.querySelector( 'input[name="urgency"]:checked' ).value,
				internal_notes: document.getElementById( 'mp-appt-notes' ).value,
				status: document.getElementById( 'mp-appt-status' ).value || 'confirmed',
				source: 'admin',
			};

			// Page "Liste des RDV" (pas de planning local à mettre à jour de façon
			// optimiste — Calendar.el n'existe pas sur cette page) : comportement
			// d'origine, on attend la réponse puis on recharge la page.
			if ( ! Calendar.el ) {
				var listRequest = id
					? apiRequest( '/appointments/' + id, 'PUT', payload )
					: apiRequest( '/appointments', 'POST', payload );

				listRequest
					.then( function () {
						Modal.close();
						document.dispatchEvent( new CustomEvent( 'mp-agenda-refresh' ) );
					} )
					.catch( function ( err ) {
						Modal.showError( err.message || cfg.i18n.saveError );
					} );
				return;
			}

			// Planning (Dashboard) : Optimistic UI — on affiche immédiatement le RDV
			// créé/modifié et on ferme la modale, la requête part en arrière-plan.
			// En cas d'échec, le changement visuel est annulé (voir .catch ci-dessous).
			var serviceSelect = document.getElementById( 'mp-appt-service' );
			var serviceName = serviceSelect && serviceSelect.selectedIndex > -1 ? serviceSelect.options[ serviceSelect.selectedIndex ].text : '';
			var technicianSelect = document.getElementById( 'mp-appt-technician' );
			var technicianName = technicianSelect && technicianSelect.selectedIndex > -1 ? technicianSelect.options[ technicianSelect.selectedIndex ].text : '';

			var endDate = new Date( startDatetime.replace( ' ', 'T' ) );
			endDate.setMinutes( endDate.getMinutes() + ( duration || 60 ) );

			var optimisticId = id || ( 'tmp-' + Date.now() );
			var optimisticAppointment = Object.assign( {}, payload, {
				id: optimisticId,
				end_datetime: formatDate( endDate ) + ' ' + pad( endDate.getHours() ) + ':' + pad( endDate.getMinutes() ) + ':00',
				service_name: serviceName,
				technician_name: technicianName,
			} );

			var previousAppointment = id
				? Calendar.appointments.filter( function ( a ) {
					return String( a.id ) === String( id );
				} )[ 0 ]
				: null;

			Calendar.addOrUpdateAppointmentLocally( optimisticAppointment );
			Modal.close();

			var request = id
				? apiRequest( '/appointments/' + id, 'PUT', payload )
				: apiRequest( '/appointments', 'POST', payload );

			request
				.then( function ( saved ) {
					// Remplace l'entrée optimiste par les données réelles renvoyées par
					// le serveur (id définitif pour une création, valeurs normalisées).
					if ( ! id ) {
						Calendar.removeAppointmentLocally( optimisticId );
					}
					Calendar.addOrUpdateAppointmentLocally( saved );
				} )
				.catch( function ( err ) {
					if ( id && previousAppointment ) {
						Calendar.addOrUpdateAppointmentLocally( previousAppointment );
					} else {
						Calendar.removeAppointmentLocally( optimisticId );
					}
					showNotification( err.message || cfg.i18n.saveError, true );
				} );
		},

		handleDelete: function () {
			var id = document.getElementById( 'mp-appt-id' ).value;
			if ( ! id ) {
				return;
			}
			if ( ! window.confirm( cfg.i18n.confirmDelete ) ) {
				return;
			}

			// Page "Liste des RDV" : comportement d'origine.
			if ( ! Calendar.el ) {
				apiRequest( '/appointments/' + id, 'DELETE' ).then( function () {
					Modal.close();
					document.dispatchEvent( new CustomEvent( 'mp-agenda-refresh' ) );
				} );
				return;
			}

			// Planning (Dashboard) : retrait optimiste immédiat, suppression en
			// arrière-plan ; restauration + notification d'erreur en cas d'échec.
			var removed = Calendar.appointments.filter( function ( a ) {
				return String( a.id ) === String( id );
			} )[ 0 ];

			Calendar.removeAppointmentLocally( id );
			Modal.close();

			apiRequest( '/appointments/' + id, 'DELETE' ).catch( function ( err ) {
				if ( removed ) {
					Calendar.addOrUpdateAppointmentLocally( removed );
				}
				showNotification( err.message || cfg.i18n.saveError, true );
			} );
		},
	};

	/* ---------------------------------------------------------------------
	 * Calendrier planning (page Dashboard)
	 * ------------------------------------------------------------------- */

	var Calendar = {
		el: null,
		view: 'day',
		currentDate: new Date(),
		technicianFilter: 'all',
		technicians: [],
		startHour: 7,
		endHour: 20,
		slotHeight: 40,

		// Copie locale des RDV/créneaux bloqués affichés, utilisée pour redessiner
		// le planning instantanément (Optimistic UI) sans repasser par le serveur.
		appointments: [],
		blockedSlots: [],

		init: function () {
			this.el = document.getElementById( 'mp-agenda-calendar' );
			if ( ! this.el ) {
				return;
			}
			this.technicians = window.mpAgendaTechnicians || [];

			document.querySelector( '.mp-agenda-nav-prev' ).addEventListener( 'click', this.navigate.bind( this, -1 ) );
			document.querySelector( '.mp-agenda-nav-next' ).addEventListener( 'click', this.navigate.bind( this, 1 ) );
			document.querySelector( '.mp-agenda-nav-today' ).addEventListener( 'click', this.goToday.bind( this ) );
			document.querySelector( '.mp-agenda-date-picker' ).addEventListener( 'change', this.onDatePicked.bind( this ) );

			document.querySelectorAll( '.mp-agenda-view-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					document.querySelectorAll( '.mp-agenda-view-btn' ).forEach( function ( b ) {
						b.classList.remove( 'is-active' );
					} );
					btn.classList.add( 'is-active' );
					Calendar.view = btn.dataset.view;
					Calendar.render();
				} );
			} );

			var filterSelect = document.querySelector( '.mp-agenda-filter-select' );
			if ( filterSelect ) {
				filterSelect.addEventListener( 'change', function () {
					Calendar.technicianFilter = filterSelect.value || 'all';
					Calendar.render();
				} );
			}

			document.querySelector( '.mp-agenda-new-appointment' ).addEventListener( 'click', function () {
				Modal.openForCreate( null, formatDate( Calendar.currentDate ), null );
			} );

			document.addEventListener( 'mp-agenda-refresh', this.render.bind( this ) );

			this.render();
		},

		navigate: function ( direction ) {
			if ( 'month' === this.view ) {
				this.currentDate.setDate( 1 );
				this.currentDate.setMonth( this.currentDate.getMonth() + direction );
				this.render();
				return;
			}
			var days = 'week' === this.view ? 7 : 1;
			this.currentDate.setDate( this.currentDate.getDate() + direction * days );
			this.render();
		},

		monthGridRange: function () {
			var y = this.currentDate.getFullYear();
			var m = this.currentDate.getMonth();
			var gridStart = startOfWeek( new Date( y, m, 1 ) );
			var lastDay = new Date( y, m + 1, 0 );
			var gridEnd = new Date( lastDay );
			var endDow = gridEnd.getDay();
			gridEnd.setDate( gridEnd.getDate() + ( 0 === endDow ? 0 : 7 - endDow ) );
			return { gridStart: gridStart, gridEnd: gridEnd, month: m };
		},

		goToday: function () {
			this.currentDate = new Date();
			this.render();
		},

		onDatePicked: function ( e ) {
			if ( e.target.value ) {
				this.currentDate = new Date( e.target.value + 'T00:00:00' );
				this.render();
			}
		},

		getFilteredTechnicians: function () {
			if ( 'all' === this.technicianFilter ) {
				return this.technicians;
			}
			var id = parseInt( this.technicianFilter, 10 );
			return this.technicians.filter( function ( t ) {
				return parseInt( t.id, 10 ) === id;
			} );
		},

		getRange: function () {
			if ( 'month' === this.view ) {
				var grid = this.monthGridRange();
				return { from: formatDate( grid.gridStart ), to: formatDate( grid.gridEnd ), start: grid.gridStart };
			}
			if ( 'week' === this.view ) {
				var start = startOfWeek( this.currentDate );
				var end = new Date( start );
				end.setDate( end.getDate() + 6 );
				return { from: formatDate( start ), to: formatDate( end ), start: start };
			}
			return { from: formatDate( this.currentDate ), to: formatDate( this.currentDate ), start: this.currentDate };
		},

		render: function () {
			var range = this.getRange();

			document.querySelector( '.mp-agenda-date-picker' ).value = formatDate( this.currentDate );
			document.querySelector( '.mp-agenda-current-label' ).textContent = this.formatLabel( range );

			this.el.innerHTML = '<div class="mp-agenda-calendar-loading">Chargement du planning…</div>';

			var technicianParam = 'all' === this.technicianFilter ? '' : this.technicianFilter;

			Promise.all( [
				apiRequest( '/appointments?from=' + range.from + '&to=' + range.to + ( technicianParam ? '&technician_id=' + technicianParam : '' ) ),
				apiRequest( '/blocked-slots?from=' + range.from + '&to=' + range.to + ( technicianParam ? '&technician_id=' + technicianParam : '' ) ),
			] )
				.then(
					function ( results ) {
						var appointments = results[ 0 ].items || [];
						var blockedSlots = results[ 1 ].items || [];
						console.log( '[MP Agenda] Blocked slots received: ' + blockedSlots.length );

						// Mémorisé pour permettre un redessin local (Optimistic UI) sans
						// nouvel appel serveur — voir addOrUpdateAppointmentLocally() et
						// removeAppointmentLocally().
						this.appointments = appointments;
						this.blockedSlots = blockedSlots;

						if ( 'month' === this.view ) {
							this.drawMonth( range, appointments, blockedSlots );
						} else {
							this.draw( range, appointments, blockedSlots );
						}
					}.bind( this )
				)
				.catch(
					function () {
						this.el.innerHTML = '<div class="mp-agenda-calendar-loading">Impossible de charger le planning.</div>';
					}.bind( this )
				);
		},

		/**
		 * Redessine le planning à partir des données déjà en mémoire (this.appointments
		 * / this.blockedSlots), sans requête serveur — utilisé par les mises à jour
		 * optimistes après création/modification/suppression d'un RDV.
		 */
		redraw: function () {
			var range = this.getRange();
			if ( 'month' === this.view ) {
				this.drawMonth( range, this.appointments, this.blockedSlots );
			} else {
				this.draw( range, this.appointments, this.blockedSlots );
			}
		},

		/**
		 * Ajoute ou remplace un RDV dans la copie locale, puis redessine.
		 */
		addOrUpdateAppointmentLocally: function ( appointment ) {
			// Comparaison en chaîne (pas parseInt) : un RDV pas encore confirmé par
			// le serveur porte un id temporaire non numérique ("tmp-…").
			var idx = this.appointments.findIndex( function ( a ) {
				return String( a.id ) === String( appointment.id );
			} );
			if ( idx > -1 ) {
				this.appointments[ idx ] = appointment;
			} else {
				this.appointments.push( appointment );
			}
			this.redraw();
		},

		/**
		 * Retire un RDV de la copie locale, puis redessine.
		 */
		removeAppointmentLocally: function ( id ) {
			this.appointments = this.appointments.filter( function ( a ) {
				return String( a.id ) !== String( id );
			} );
			this.redraw();
		},

		formatLabel: function ( range ) {
			var months = [ 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' ];
			if ( 'month' === this.view ) {
				return months[ this.currentDate.getMonth() ] + ' ' + this.currentDate.getFullYear();
			}
			if ( 'week' === this.view ) {
				var start = new Date( range.from );
				var end = new Date( range.to );
				return start.getDate() + ' - ' + end.getDate() + ' ' + months[ end.getMonth() ] + ' ' + end.getFullYear();
			}
			var d = new Date( range.from );
			return d.getDate() + ' ' + months[ d.getMonth() ] + ' ' + d.getFullYear();
		},

		buildColumns: function ( range ) {
			var techs = this.getFilteredTechnicians();
			var columns = [];

			if ( 'week' === this.view ) {
				for ( var i = 0; i < 7; i++ ) {
					var day = new Date( range.start );
					day.setDate( day.getDate() + i );
					techs.forEach( function ( tech ) {
						columns.push( { date: formatDate( day ), dateObj: day, technicianId: tech.id, technicianName: tech.name } );
					} );
				}
			} else {
				techs.forEach( function ( tech ) {
					columns.push( { date: range.from, dateObj: new Date( range.from ), technicianId: tech.id, technicianName: tech.name } );
				} );
			}

			return columns;
		},

		draw: function ( range, appointments, blockedSlots ) {
			var self = this;
			var columns = this.buildColumns( range );
			var totalSlots = ( this.endHour - this.startHour ) * 2;
			var dayNames = [ 'Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam' ];

			var html = '<div class="mp-agenda-cal-grid">';

			// En-tête.
			html += '<div class="mp-agenda-cal-header" style="grid-template-columns:64px repeat(' + columns.length + ', 1fr);">';
			html += '<div class="mp-agenda-cal-header-cell mp-agenda-cal-time-col"></div>';
			columns.forEach( function ( col ) {
				var nameHtml = '<span class="mp-agenda-cal-tech-name">' + col.technicianName + '</span>';
				var label = 'week' === self.view ? dayNames[ col.dateObj.getDay() ] + ' ' + col.dateObj.getDate() + '<br>' + nameHtml : nameHtml;
				html += '<div class="mp-agenda-cal-header-cell">' + label + '</div>';
			} );
			html += '</div>';

			// Corps.
			html += '<div class="mp-agenda-cal-body">';
			html += '<div class="mp-agenda-cal-times">';
			for ( var s = 0; s < totalSlots; s++ ) {
				var hour = self.startHour + Math.floor( s / 2 );
				var minute = ( s % 2 ) * 30;
				html += '<div class="mp-agenda-cal-time-slot">' + ( 0 === minute ? pad( hour ) + ':00' : '' ) + '</div>';
			}
			html += '</div>';

			html += '<div class="mp-agenda-cal-columns">';
			columns.forEach( function ( col, colIndex ) {
				html += '<div class="mp-agenda-cal-column" data-col-index="' + colIndex + '">';
				for ( var slot = 0; slot < totalSlots; slot++ ) {
					html += '<div class="mp-agenda-cal-slot" data-slot="' + slot + '"></div>';
				}
				html += '</div>';
			} );
			html += '</div>'; // .mp-agenda-cal-columns
			html += '</div>'; // .mp-agenda-cal-body
			html += '</div>'; // .mp-agenda-cal-grid

			this.el.innerHTML = html;

			// Ajuste dynamiquement le template en colonnes réelles (grid-template-columns identique header/colonnes).
			var columnsWrap = this.el.querySelector( '.mp-agenda-cal-columns' );

			// Empty-slot click handlers.
			this.el.querySelectorAll( '.mp-agenda-cal-column' ).forEach( function ( colEl ) {
				var colIndex = parseInt( colEl.dataset.colIndex, 10 );
				var col = columns[ colIndex ];
				colEl.querySelectorAll( '.mp-agenda-cal-slot' ).forEach( function ( slotEl ) {
					slotEl.addEventListener( 'click', function () {
						var slotIndex = parseInt( slotEl.dataset.slot, 10 );
						var hour = self.startHour + Math.floor( slotIndex / 2 );
						var minute = ( slotIndex % 2 ) * 30;
						Modal.openForCreate( col.technicianId, col.date, pad( hour ) + ':' + pad( minute ) );
					} );
				} );
			} );

			// Positionne les événements (RDV + créneaux bloqués).
			var allEvents = appointments.map( function ( a ) {
				return Object.assign( {}, a, { isBlocked: false } );
			} ).concat(
				blockedSlots.map( function ( b ) {
					return Object.assign( {}, b, { isBlocked: true, status: 'blocked' } );
				} )
			);

			allEvents.forEach( function ( event ) {
				var start = new Date( event.start_datetime.replace( ' ', 'T' ) );
				var end = new Date( event.end_datetime.replace( ' ', 'T' ) );
				var dateStr = formatDate( start );

				columns.forEach( function ( col, colIndex ) {
					if ( col.date !== dateStr ) {
						return;
					}
					if ( ! event.isBlocked && parseInt( col.technicianId, 10 ) !== parseInt( event.technician_id, 10 ) ) {
						return;
					}
					if ( event.isBlocked && parseInt( col.technicianId, 10 ) !== parseInt( event.technician_id, 10 ) ) {
						return;
					}

					var gridMinutes = totalSlots * 30;
					var startMinutes = ( start.getHours() - self.startHour ) * 60 + start.getMinutes();
					var endMinutes;

					if ( formatDate( end ) !== dateStr || end <= start ) {
						// L'événement déborde sur le jour suivant (ex. créneau bloqué
						// "toute la journée" 00:00 -> 00:00 le lendemain : end.getHours()
						// vaut alors 0, exactement comme start, ce qui donnait une
						// hauteur nulle et rendait le bloc invisible). On borne
						// l'affichage à la fin de la grille visible de cette journée.
						endMinutes = gridMinutes;
					} else {
						endMinutes = ( end.getHours() - self.startHour ) * 60 + end.getMinutes();
					}

					// Borne aussi le début : un événement commençant avant l'heure de
					// départ de la grille (ex. RDV/blocage à 7h avec grille à partir de
					// 8h) doit s'afficher depuis le haut plutôt que d'être poussé
					// hors-écran par un top négatif.
					startMinutes = Math.max( startMinutes, 0 );
					endMinutes = Math.min( endMinutes, gridMinutes );

					var top = ( startMinutes / 30 ) * self.slotHeight;
					var height = Math.max( ( ( endMinutes - startMinutes ) / 30 ) * self.slotHeight, 20 );

					var colEl = self.el.querySelectorAll( '.mp-agenda-cal-column' )[ colIndex ];
					var eventEl = document.createElement( 'div' );
					eventEl.className = 'mp-agenda-event mp-agenda-event-' + ( event.isBlocked ? 'blocked' : event.status );
					eventEl.style.top = top + 'px';
					eventEl.style.height = height + 'px';

					if ( event.isBlocked ) {
						eventEl.innerHTML = '<strong>Indisponible</strong>' + ( event.reason || '' );
					} else {
						eventEl.innerHTML = '<strong>' + escapeHtml( event.client_name ) + '</strong>' + pad( start.getHours() ) + ':' + pad( start.getMinutes() ) + ' — ' + escapeHtml( event.service_name || event.intervention_type || '' );
						eventEl.addEventListener( 'click', function ( e ) {
							e.stopPropagation();
							Modal.openForEdit( event );
						} );
					}

					colEl.appendChild( eventEl );
				} );
			} );
		},

		drawMonth: function ( range, appointments, blockedSlots ) {
			var self = this;
			var dayNames = [ 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim' ];

			var grid = this.monthGridRange();
			var gridStart = grid.gridStart;
			var gridEnd = grid.gridEnd;
			var month = grid.month;
			var totalDays = Math.round( ( gridEnd - gridStart ) / 86400000 ) + 1;
			var totalCells = Math.ceil( totalDays / 7 ) * 7;

			// Nom du commercial par identifiant (pour l'affichage dans les badges).
			var techById = {};
			this.technicians.forEach( function ( t ) {
				techById[ parseInt( t.id, 10 ) ] = t.name;
			} );

			// Regroupe les événements (RDV + créneaux bloqués) par date, filtrés par commercial.
			var allEvents = appointments.map( function ( a ) {
				return { event: Object.assign( {}, a, { isBlocked: false } ) };
			} ).concat(
				blockedSlots.map( function ( b ) {
					return { event: Object.assign( {}, b, { isBlocked: true, status: 'blocked' } ) };
				} )
			);

			var byDate = {};
			allEvents.forEach( function ( entry ) {
				var ev = entry.event;
				if ( 'all' !== self.technicianFilter && parseInt( ev.technician_id, 10 ) !== parseInt( self.technicianFilter, 10 ) ) {
					return;
				}
				var start = new Date( ev.start_datetime.replace( ' ', 'T' ) );
				var key = formatDate( start );
				entry.start = start;
				( byDate[ key ] = byDate[ key ] || [] ).push( entry );
			} );
			Object.keys( byDate ).forEach( function ( k ) {
				byDate[ k ].sort( function ( a, b ) {
					return a.start - b.start;
				} );
			} );

			var todayStr = formatDate( new Date() );

			var html = '<div class="mp-agenda-month">';
			html += '<div class="mp-agenda-month-weekdays">';
			dayNames.forEach( function ( d ) {
				html += '<div class="mp-agenda-month-weekday">' + d + '</div>';
			} );
			html += '</div>';
			html += '<div class="mp-agenda-month-grid">';

			var cursor = new Date( gridStart );
			for ( var c = 0; c < totalCells; c++ ) {
				var dateStr = formatDate( cursor );
				var cls = 'mp-agenda-month-cell';
				if ( cursor.getMonth() !== month ) {
					cls += ' is-outside';
				}
				if ( dateStr === todayStr ) {
					cls += ' is-today';
				}
				html += '<div class="' + cls + '" data-date="' + dateStr + '">';
				html += '<div class="mp-agenda-month-daynum">' + cursor.getDate() + '</div>';
				html += '<div class="mp-agenda-month-events">';

				( byDate[ dateStr ] || [] ).forEach( function ( entry ) {
					var ev = entry.event;
					var time = pad( entry.start.getHours() ) + ':' + pad( entry.start.getMinutes() );
					var statusClass = ev.isBlocked ? 'blocked' : ev.status;

					if ( ev.isBlocked ) {
						html += '<span class="mp-agenda-month-event mp-agenda-event-' + statusClass + '">' +
							'<span class="mp-agenda-month-event-time">' + time + '</span> ' +
							escapeHtml( ev.reason || 'Indisponible' ) + '</span>';
						return;
					}

					var tech = techById[ parseInt( ev.technician_id, 10 ) ] || '';
					html += '<button type="button" class="mp-agenda-month-event mp-agenda-event-' + statusClass + '" data-appt-id="' + ev.id + '">' +
						'<span class="mp-agenda-month-event-time">' + time + '</span> ' +
						escapeHtml( ev.client_name || '' ) +
						( tech ? ' <span class="mp-agenda-month-event-tech">· ' + escapeHtml( tech ) + '</span>' : '' ) +
						'</button>';
				} );

				html += '</div></div>';
				cursor.setDate( cursor.getDate() + 1 );
			}

			html += '</div></div>';
			this.el.innerHTML = html;

			// Clic sur un RDV → modal d'édition.
			this.el.querySelectorAll( '.mp-agenda-month-event[data-appt-id]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					// Comparaison en chaîne : un RDV pas encore confirmé par le serveur
					// (Optimistic UI) porte un id temporaire non numérique ("tmp-…").
					var id = btn.dataset.apptId;
					var appt = appointments.filter( function ( a ) {
						return String( a.id ) === String( id );
					} )[ 0 ];
					if ( appt ) {
						Modal.openForEdit( appt );
					}
				} );
			} );

			// Clic sur un jour → bascule en vue Jour sur ce jour.
			this.el.querySelectorAll( '.mp-agenda-month-cell' ).forEach( function ( cell ) {
				cell.addEventListener( 'click', function () {
					Calendar.currentDate = new Date( cell.dataset.date + 'T00:00:00' );
					Calendar.view = 'day';
					document.querySelectorAll( '.mp-agenda-view-btn' ).forEach( function ( b ) {
						b.classList.toggle( 'is-active', 'day' === b.dataset.view );
					} );
					Calendar.render();
				} );
			} );
		},
	};

	function escapeHtml( str ) {
		if ( ! str ) {
			return '';
		}
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	/* ---------------------------------------------------------------------
	 * Synchronisation Google manuelle (planning + page Techniciens)
	 *
	 * Le cron WordPress (mp_agenda_google_sync_cron) n'est déclenché que par
	 * une visite du site et peut donc être retardé sur certains hébergements.
	 * Ces boutons appellent directement POST /google/sync pour forcer
	 * immédiatement l'import des événements Google Agenda (RDV + créneaux
	 * bloqués) sans attendre le prochain passage du cron.
	 * ------------------------------------------------------------------- */

	function initGoogleSync() {
		var buttons = document.querySelectorAll( '.mp-agenda-sync-google-btn' );
		console.log( '[MP Agenda] initGoogleSync : ' + buttons.length + ' bouton(s) trouvé(s).' );

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				console.log( '[MP Agenda] Clic sur "Synchroniser Google" — envoi de POST /google/sync via admin-ajax.php.' );

				var originalText = btn.textContent;
				var statusEl = btn.parentElement.querySelector( '.mp-agenda-sync-status' );

				btn.disabled = true;
				btn.textContent = cfg.i18n.syncing;
				if ( statusEl ) {
					statusEl.textContent = '';
				}

				apiRequest( '/google/sync', 'POST' )
					.then( function ( data ) {
						console.log( '[MP Agenda] Synchronisation Google réussie. Réponse complète :', data );
						if ( data && Array.isArray( data.logs ) ) {
							console.log( '[MP Agenda] Journal de synchronisation (' + data.logs.length + ' ligne(s)) :' );
							data.logs.forEach( function ( line ) {
								console.log( line );
							} );
						}
						btn.disabled = false;
						btn.textContent = originalText;
						if ( statusEl ) {
							statusEl.textContent = cfg.i18n.syncSuccess;
						}
						document.dispatchEvent( new CustomEvent( 'mp-agenda-refresh' ) );
					} )
					.catch( function ( err ) {
						console.error( '[MP Agenda] Échec de la synchronisation Google :', err );
						btn.disabled = false;
						btn.textContent = originalText;
						if ( statusEl ) {
							statusEl.textContent = err.message || cfg.i18n.syncError;
						}
					} );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Liste des rendez-vous (page Appointments)
	 * ------------------------------------------------------------------- */

	function initAppointmentsList() {
		var table = document.querySelector( '.mp-agenda-table' );
		if ( ! table ) {
			return;
		}

		table.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'mp-agenda-edit-appointment' ) ) {
				var id = e.target.dataset.id;
				apiRequest( '/appointments/' + id ).then( function ( data ) {
					Modal.openForEdit( data );
				} );
			}

			if ( e.target.classList.contains( 'mp-agenda-delete-appointment' ) ) {
				if ( ! window.confirm( cfg.i18n.confirmDelete ) ) {
					return;
				}
				var delId = e.target.dataset.id;
				apiRequest( '/appointments/' + delId, 'DELETE' ).then( function () {
					window.location.reload();
				} );
			}
		} );

		document.addEventListener( 'mp-agenda-refresh', function () {
			window.location.reload();
		} );
	}

	/* ---------------------------------------------------------------------
	 * Initialisation générale
	 * ------------------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initMediaPicker();
		initInterventionTypes();
		Modal.init();
		Calendar.init();
		initAppointmentsList();
		initGoogleSync();
	} );
} )();
