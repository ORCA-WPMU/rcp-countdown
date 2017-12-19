<?php
/**
 * PayButton shortcode class
 *
 * @package svbk-rcp-countdown
 * @author Brando Meniconi <b.meniconi@silverbackstudio.it>
 */

namespace Svbk\WP\Plugins\RCP\Countdown;

use RCP_Discounts;
use DateTime;
use Svbk\WP\Shortcakes\Shortcake as Base;

/**
 * Paybutton Countdown class
 */
class Expiration extends Base {

	/**
	 * The shortcode ID.
	 *
	 * @access public
	 * @var string $shortcode_id Description.
	 */
	public $shortcode_id = 'discount_expiration';

	/**
	 *
	 * The shortocode icon.
	 *
	 * @access public
	 * @var string $icon The shortocode icon.
	 */
	public $icon = 'dashicons-backup';

	/**
	 * The shortcode CSS classes.
	 *
	 * @access public
	 * @var string $classes The shortcode CSS classes applied to wrapper element
	 */
	public $classes = array( 'expiration' );

	/**
	 *
	 * Shortcode interface defaults.
	 *
	 * @access public
	 * @var array $defaults Shortcode interface defaults.
	 */
	public $defaults = array(
		'membership_level' => '',
		'template' => '%s',
		'show_if_expired' => false,
	);

	/**
	 *
	 * Shortocde elements render order.
	 *
	 * @access public
	 * @var array $renderOrder Shortocde elements render order.
	 */
	public $renderOrder = array(
		'content',		
		'date',
	);

	/**
	 * The shortcode title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Discount Expiration Date', 'svbk-rcp-countdown' );
	}

	/**
	 * The shorcode UI fields.
	 *
	 * @return array
	 */
	public function fields() {

		return array(
			'membership_level' => array(
				'label'  => esc_html__( 'Membership Level', 'svbk-rcp-countdown' ),
				'attr'   => 'membership_level',
				'type'   => 'select',
				'options' => wp_list_pluck( rcp_get_subscription_levels( 'active' ), 'name', 'id' ),
			),
			'template' => array(
				'label'  => esc_html__( 'Template', 'svbk-rcp-countdown' ),
				'attr'   => 'template',
				'type'   => 'text',
			),			
			'show_if_expired' => array(
				'label'  => esc_html__( 'Show if Expired', 'svbk-rcp-countdown' ),
				'attr'   => 'show_if_expired',
				'type'   => 'checkbox',
			),			
		);
	}



	/**
	 * Render & return output elements.
	 *
	 * @param array $attr The shortcode attributes.
	 * @param text  $content Optional. The shortcode content.
	 * @param text  $shortcode_tag Optional. The triggered shortcode tag name.
	 *
	 * @return array
	 */
	public function renderOutput( $attr, $content, $shortcode_tag ) {

		$attr = $this->shortcode_atts( $this->defaults, $attr, $shortcode_tag );
	
		$show_if_expired = filter_var($attr['show_if_expired'], FILTER_VALIDATE_BOOLEAN);
		
		$subscription = rcp_get_subscription_details( $attr['membership_level'] );

		$output = parent::renderOutput( $attr, $content, $shortcode_tag );

		if ( ! $subscription ) {
			$output['content'] = __( 'WARNING: Membership level not found', 'svbk-rcp-countdown' );
			return $output;
		}

		$discounts = new RCP_Discounts();
		$main_discount = main_discount( $attr['membership_level'] );
		
		if( !$main_discount ){
			return $output;
		}
		
		$expire = $discounts->get_expiration( $main_discount->id ) ; 
	
		if( ! $expire || ( $discounts->is_expired() && ! $show_if_expired) ) {
			return $output;
		}

		if( $attr['template'] ) {
			$output['content'] = sprintf( $attr['template'], date_i18n( get_option( 'date_format' ), strtotime( $expire ) ) );
		} else {
			$output['content'] = date_i18n( get_option( 'date_format' ), strtotime( $expire ) ) ;
		}
		
		return $output;

	}

}

