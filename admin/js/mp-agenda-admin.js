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

			var payload = {
				technician_id: parseInt( document.getElementById( 'mp-appt-technician' ).value, 10 ),
				start_datetime: date + ' ' + time + ':00',
				duration: parseInt( document.getElementById( 'mp-appt-duration' ).value, 10 ),
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

			var request = id
				? apiRequest( '/appointments/' + id, 'PUT', payload )
				: apiRequest( '/appointments', 'POST', payload );

			request
				.then( function () {
					Modal.close();
					document.dispatchEvent( new CustomEvent( 'mp-agenda-refresh' ) );
				} )
				.catch( function ( err ) {
					Modal.showError( err.message || cfg.i18n.saveError );
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
			apiRequest( '/appointments/' + id, 'DELETE' ).then( function () {
				Modal.close();
				document.dispatchEvent( new CustomEvent( 'mp-agenda-refresh' ) );
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

			document.querySelectorAll( '.mp-agenda-filter-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					document.querySelectorAll( '.mp-agenda-filter-btn' ).forEach( function ( b ) {
						b.classList.remove( 'is-active' );
					} );
					btn.classList.add( 'is-active' );
					Calendar.technicianFilter = btn.dataset.technician;
					Calendar.render();
				} );
			} );

			document.querySelector( '.mp-agenda-new-appointment' ).addEventListener( 'click', function () {
				Modal.openForCreate( null, formatDate( Calendar.currentDate ), null );
			} );

			document.addEventListener( 'mp-agenda-refresh', this.render.bind( this ) );

			this.render();
		},

		navigate: function ( direction ) {
			var days = 'week' === this.view ? 7 : 1;
			this.currentDate.setDate( this.currentDate.getDate() + direction * days );
			this.render();
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
						this.draw( range, results[ 0 ].items || [], results[ 1 ].items || [] );
					}.bind( this )
				)
				.catch(
					function () {
						this.el.innerHTML = '<div class="mp-agenda-calendar-loading">Impossible de charger le planning.</div>';
					}.bind( this )
				);
		},

		formatLabel: function ( range ) {
			var months = [ 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' ];
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

					var startMinutes = ( start.getHours() - self.startHour ) * 60 + start.getMinutes();
					var endMinutes = ( end.getHours() - self.startHour ) * 60 + end.getMinutes();
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
						console.log( '[MP Agenda] Synchronisation Google réussie.', data );
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
