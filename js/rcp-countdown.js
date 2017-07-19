/**
 *  Svbk RCP Countdown manager
 *
 * @package svbk-rcp-countdown
 * @author Brando Meniconi <b.meniconi@silverbackstudio.it>
 *
 *	global svbkRcpCountdown
 */

(function ($) {

	var items = svbkRcpCountdown.length;

	for (var i = 0; i < items; i++) {
		if ( svbkRcpCountdown[i].discount_expires ) {
			$( '.countdown.level-' + svbkRcpCountdown[i].id )
			.countdown( svbkRcpCountdown[i].discount_expires, { elapse: false } )
				.on('update.countdown', function (event) {

					if (event.elapsed) {
						$( '.prices.level-' + $( this ).data( 'level' ) ).removeClass( 'has-discount' );
						$( this ).countdown( 'pause' );
					}

					$( this ).text( event.strftime( '%D:%H:%M:%S' ) );
				});
		}
	}

})(jQuery);
