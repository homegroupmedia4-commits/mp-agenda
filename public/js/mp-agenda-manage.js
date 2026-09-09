/**
 * MP Agenda — Page publique "Gérer mon rendez-vous" (vanilla JS).
 *
 * Permet au client, depuis le lien sécurisé par token reçu par email, de
 * reprogrammer ou d'annuler son rendez-vous. Tous les appels passent par
 * admin-ajax.php (action mp_agenda_api), l'authentification métier reposant
 * sur le couple appointment_id + token.
 */
( function () {
	'use strict';

	var cfg = window.mpAgendaManage || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'mp-agenda-manage-root' );
		if ( ! root ) {
			return;
		}
		new MPAgendaManage( root ).init();
	} );

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

	function pad( n ) {
		return n < 10 ? '0' + n : '' + n;
	}

	function formatDate( date ) {
		return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() );
	}

	function MPAgendaManage( root ) {
		this.root = root;
		this.appointmentId = parseInt( root.dataset.appointmentId, 10 );
		this.token = root.dataset.token;

		this.technicianId = 0;
		this.duration = 60;
		this.dayMap = [ 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' ];
		this.workingHours = {};

		this.currentMonth = new Date();
		this.currentMonth.setDate( 1 );
		this.selectedDate = null;
		this.selectedTime = null;
	}

	MPAgendaManage.prototype.init = function () {
		var self = this;

		this.bindCalendarNav();
		this.bindActions();

		apiRequest(
			'/appointments/manage?appointment_id=' + this.appointmentId + '&token=' + encodeURIComponent( this.token ),
			'GET'
		)
			.then( function ( data ) {
				self.technicianId = parseInt( data.technician_id, 10 );
				self.duration = parseInt( data.duration, 10 ) || 60;
				self.workingHours = data.working_hours || {};
				if ( 'cancelled' === data.status ) {
					self.showCancelledState();
					return;
				}
				self.renderCalendar();
			} )
			.catch( function ( err ) {
				self.showMessage( err.message || cfg.i18n.genericError, 'is-error' );
			} );
	};

	MPAgendaManage.prototype.bindCalendarNav = function () {
		var self = this;
		var prev = document.getElementById( 'mp-agenda-manage-cal-prev' );
		var next = document.getElementById( 'mp-agenda-manage-cal-next' );
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				self.currentMonth.setMonth( self.currentMonth.getMonth() - 1 );
				self.renderCalendar();
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				self.currentMonth.setMonth( self.currentMonth.getMonth() + 1 );
				self.renderCalendar();
			} );
		}
	};

	MPAgendaManage.prototype.bindActions = function () {
		var self = this;

		var confirmBtn = document.getElementById( 'mp-agenda-manage-confirm' );
		if ( confirmBtn ) {
			confirmBtn.addEventListener( 'click', function () {
				self.submitReschedule();
			} );
		}

		var cancelBtn = document.getElementById( 'mp-agenda-manage-cancel-btn' );
		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( cfg.i18n.confirmCancel ) ) {
					return;
				}
				self.submitCancellation();
			} );
		}
	};

	MPAgendaManage.prototype.isDayWorked = function ( date ) {
		var config = this.workingHours[ this.dayMap[ date.getDay() ] ];
		return !! ( config && config.active );
	};

	MPAgendaManage.prototype.renderCalendar = function () {
		var self = this;
		var year = this.currentMonth.getFullYear();
		var month = this.currentMonth.getMonth();

		document.getElementById( 'mp-agenda-manage-cal-label' ).textContent = cfg.i18n.months[ month ] + ' ' + year;

		var weekdaysEl = document.getElementById( 'mp-agenda-manage-cal-weekdays' );
		weekdaysEl.innerHTML = '';
		[ 1, 2, 3, 4, 5, 6, 0 ].forEach( function ( dayIndex ) {
			var span = document.createElement( 'span' );
			span.textContent = cfg.i18n.days[ dayIndex ];
			weekdaysEl.appendChild( span );
		} );

		var daysEl = document.getElementById( 'mp-agenda-manage-cal-days' );
		daysEl.innerHTML = '';

		var firstOfMonth = new Date( year, month, 1 );
		var startOffset = ( firstOfMonth.getDay() + 6 ) % 7;
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

			if ( date < today || ! self.isDayWorked( date ) ) {
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

	MPAgendaManage.prototype.selectDate = function ( date, cellEl ) {
		this.selectedDate = formatDate( date );
		this.selectedTime = null;
		this.updateConfirmState();

		this.root.querySelectorAll( '.mp-agenda-cal-day' ).forEach( function ( c ) {
			c.classList.remove( 'is-selected' );
		} );
		cellEl.classList.add( 'is-selected' );

		document.getElementById( 'mp-agenda-manage-slots-title' ).textContent =
			date.toLocaleDateString( 'fr-FR', { weekday: 'long', day: 'numeric', month: 'long' } );

		this.loadSlots();
	};

	MPAgendaManage.prototype.loadSlots = function () {
		var self = this;
		var slotsEl = document.getElementById( 'mp-agenda-manage-slots' );
		slotsEl.innerHTML = '<div class="mp-agenda-loading">' + cfg.i18n.loading + '</div>';

		apiRequest(
			'/available-slots?technician_id=' + this.technicianId + '&date=' + this.selectedDate + '&duration=' + this.duration,
			'GET'
		)
			.then( function ( data ) {
				self.renderSlots( data.slots || [] );
			} )
			.catch( function () {
				slotsEl.innerHTML = '<div class="mp-agenda-empty">' + cfg.i18n.genericError + '</div>';
			} );
	};

	MPAgendaManage.prototype.renderSlots = function ( times ) {
		var self = this;
		var slotsEl = document.getElementById( 'mp-agenda-manage-slots' );
		slotsEl.innerHTML = '';

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
				self.selectTime( time, btn );
			} );
			slotsEl.appendChild( btn );
		} );
	};

	MPAgendaManage.prototype.selectTime = function ( time, btnEl ) {
		this.selectedTime = time;
		this.root.querySelectorAll( '.mp-agenda-slot-btn' ).forEach( function ( b ) {
			b.classList.remove( 'is-selected' );
		} );
		btnEl.classList.add( 'is-selected' );
		this.updateConfirmState();
	};

	MPAgendaManage.prototype.updateConfirmState = function () {
		var btn = document.getElementById( 'mp-agenda-manage-confirm' );
		if ( btn ) {
			btn.disabled = ! ( this.selectedDate && this.selectedTime );
		}
	};

	MPAgendaManage.prototype.submitReschedule = function () {
		var self = this;
		var btn = document.getElementById( 'mp-agenda-manage-confirm' );
		btn.disabled = true;
		this.hideMessage();

		apiRequest( '/appointments/manage', 'PUT', {
			appointment_id: this.appointmentId,
			token: this.token,
			date: this.selectedDate,
			time: this.selectedTime,
		} )
			.then( function ( data ) {
				var start = new Date( data.start_datetime.replace( ' ', 'T' ) );
				var label = pad( start.getDate() ) + '/' + pad( start.getMonth() + 1 ) + '/' + start.getFullYear() +
					' à ' + pad( start.getHours() ) + ':' + pad( start.getMinutes() );
				document.getElementById( 'mp-agenda-manage-datetime' ).textContent = label;
				self.showMessage( cfg.i18n.rescheduleSuccess, 'is-success' );
				self.selectedDate = null;
				self.selectedTime = null;
				self.updateConfirmState();
				document.getElementById( 'mp-agenda-manage-slots' ).innerHTML = '';
				document.getElementById( 'mp-agenda-manage-slots-title' ).textContent = '';
				self.renderCalendar();
			} )
			.catch( function ( err ) {
				self.showMessage( err.message || cfg.i18n.genericError, 'is-error' );
				self.updateConfirmState();
			} );
	};

	MPAgendaManage.prototype.submitCancellation = function () {
		var self = this;
		var btn = document.getElementById( 'mp-agenda-manage-cancel-btn' );
		btn.disabled = true;
		this.hideMessage();

		apiRequest( '/appointments/manage/cancel', 'POST', {
			appointment_id: this.appointmentId,
			token: this.token,
		} )
			.then( function () {
				self.showCancelledState();
				self.showMessage( cfg.i18n.cancelSuccess, 'is-success' );
			} )
			.catch( function ( err ) {
				btn.disabled = false;
				self.showMessage( err.message || cfg.i18n.genericError, 'is-error' );
			} );
	};

	MPAgendaManage.prototype.showCancelledState = function () {
		var notice = document.getElementById( 'mp-agenda-manage-cancelled-notice' );
		var reschedule = document.getElementById( 'mp-agenda-manage-reschedule' );
		var cancelWrap = document.getElementById( 'mp-agenda-manage-cancel-wrap' );
		if ( notice ) {
			notice.hidden = false;
		}
		if ( reschedule ) {
			reschedule.hidden = true;
		}
		if ( cancelWrap ) {
			cancelWrap.hidden = true;
		}
	};

	MPAgendaManage.prototype.showMessage = function ( text, cls ) {
		var el = document.getElementById( 'mp-agenda-manage-message' );
		if ( ! el ) {
			return;
		}
		el.className = 'mp-agenda-manage-message ' + ( cls || '' );
		el.textContent = text;
		el.hidden = false;
	};

	MPAgendaManage.prototype.hideMessage = function () {
		var el = document.getElementById( 'mp-agenda-manage-message' );
		if ( el ) {
			el.hidden = true;
		}
	};
} )();
