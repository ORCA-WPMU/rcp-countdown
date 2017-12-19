<?php
/**
 * Main RCP Countdown class
 *
 * @package svbk-rcp-countdown
 * @author Brando Meniconi <b.meniconi@silverbackstudio.it>
 */

namespace Svbk\WP\Plugins\RCP\Countdown;

use WP_Session;
use DateTimeImmutable;
use DateInterval;
use RCP_Registration;
use RCP_Discounts;
use DateTime;

/**
 * Main RCP Countdown class
 */
class Discount {

	/**
	 * Triggers the countdown for a specific subsciption level.
	 *
	 * @param object $level The subscription level object.
	 *
	 * @return DateTimeImmutable The expiration time
	 */
	public static function trigger_expiration( $level ) {

		$user_id = apply_filters( 'svbk_rcp_countdown_current_user', is_user_logged_in() ? get_current_user_id() : null );
		$expiration = self::get_user_expiration( $level, $user_id );

		if ( ! $expiration ) {
			$expiration = self::set_user_expiration( $level, $user_id );
		}

		do_action( 'svbk_rcp_countdown_trigger_expiration', $level, $user_id );

		return $expiration;
	}

	/**
	 * Get expiration time for a specific Level and User
	 *
	 * @param object $level The subscription level object.
	 * @param int    $user_id Optional. The registered user id, if not set uses current session only.
	 *
	 * @return DateTimeImmutable The expiration time
	 */
	public static function get_user_expiration( $level, $user_id = null ) {

		$key = 'svbk_rcp_ctd_' . $level->role . '_discount_expires';
		$expire = null;
		
		$discount = self::main_discount($level->id);
		
		if( $discount ) {

			$discounts_db = new \RCP_Discounts();
			
			$expire = $discounts_db->get_expiration( $discount->id );
			
			if ( $expire ) {
				$expdate = DateTime::createFromFormat('Y-m-d', $expire);
				$expdate->setTime(23, 59, 59);
				return $expdate;
			}
			
		}

		if ( $user_id && ( $user_expiration = get_user_meta( $user_id, $key, true ) ) ) {
			$expire = $user_expiration;
		 } else { 
			$session = WP_Session::get_instance();
			if ( isset( $session[ $key ] ) ) {
				$expire = $session[ $key ];
			}
		}
		
		return apply_filters( 'svbk_rcp_countdown_get_user_expiration', $expire, $level, $user_id );
	}

	/**
	 * Set expiration time for a specific Level and User
	 *
	 * @param object $level The subscription level object.
	 * @param int    $user_id Optional. The registered user id, if not set uses current session only.
	 *
	 * @return DateTimeImmutable The expiration time set
	 */
	public static function set_user_expiration( $level, $user_id = null ) {

		$discount_duration = self::get_discount_duration( $level );
		$now = new DateTimeImmutable( 'NOW' );
		$key = 'svbk_rcp_ctd_' . $level->role . '_discount_expires';

		$expiration = apply_filters( 'svbk_rcp_countdown_set_user_expiration', $now->add( new DateInterval( 'PT' . $discount_duration . 'S' ) ), $level, $user_id );

		if ( $user_id ) {
			add_user_meta( $user_id, $key, $expiration, true );
		}

		$session = WP_Session::get_instance();
		$session[ $key ] = $expiration;

		return $expiration;
	}

	/**
	 * Get the main discount duration for a specific subscription level
	 *
	 * @param object $level The subscription level object.
	 *
	 * @return int The discount duration in seconds
	 */
	public static function get_discount_duration( $level ) {

		global $rcp_levels_db;

		$discount_duration_base = $rcp_levels_db->get_meta( $level->id, 'discount_duration', true );
		$discount_duration_unit = $rcp_levels_db->get_meta( $level->id, 'discount_duration_unit', true );

		$multipliers = array(
			'minute' => MINUTE_IN_SECONDS,
			'hour' => HOUR_IN_SECONDS,
			'day' => DAY_IN_SECONDS,
		);

		if ( isset( $multipliers[ $discount_duration_unit ] ) ) {
			$discount_duration = intval( $discount_duration_base ) * $multipliers[ $discount_duration_unit ];
		} else {
			$discount_duration = intval( $discount_duration_base ) * MINUTE_IN_SECONDS;
		}

		return apply_filters( 'svbk_rcp_countdown_level_expire', $discount_duration, $level, $discount_duration_base, $discount_duration_unit );
	}

	/**
	 * Check if the countdown is elapsed for the current user
	 *
	 * @param int $level_id The subscription level ID.
	 *
	 * @return bool
	 */
	public static function has_expired( $level_id ) {

		global $rcp_levels_db;

		$user_id = apply_filters( 'svbk_rcp_countdown_current_user', is_user_logged_in() ? get_current_user_id() : null );
		$level = $rcp_levels_db->get_level( $level_id );

		$expiration = self::get_user_expiration( $level, $user_id );
		$now = new DateTimeImmutable( 'NOW' );

		return $expiration && ( $now > $expiration );
	}


}
