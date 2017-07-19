<?php
/**
 * PayButton shortcode class
 *
 * @package svbk-rcp-countdown
 * @author Brando Meniconi <b.meniconi@silverbackstudio.it>
 */

namespace Svbk\WP\Plugins\RCP\Countdown;

use RCP_Discounts;
use Svbk\WP\Helpers;
use Svbk\WP\Shortcakes\Shortcake as Base;

/**
 * Paybutton Countdown class
 */
class PayButton extends Base {

	/**
	 * The shortcode ID.
	 *
	 * @access public
	 * @var string $shortcode_id Description.
	 */
	public $shortcode_id = 'pay_button';

	/**
	 *
	 * The shortocode icon.
	 *
	 * @access public
	 * @var string $icon The shortocode icon.
	 */
	public $icon = 'dashicons-cart';

	/**
	 * The shortcode CSS classes.
	 *
	 * @access public
	 * @var string $classes The shortcode CSS classes applied to wrapper element
	 */
	public $classes = array( 'pay-button' );

	/**
	 *
	 * Shortcode interface defaults.
	 *
	 * @access public
	 * @var array $defaults Shortcode interface defaults.
	 */
	public $defaults = array(
		'membership_level' => '',
		'discount' => '',
		'payment_page' => '',
		'show_countdown' => true,
		'show_discount' => true,
		'button_label' => '',
	);

	/**
	 *
	 * Shortocde elements render order.
	 *
	 * @access public
	 * @var array $renderOrder Shortocde elements render order.
	 */
	public $renderOrder = array(
		'wrapperStart',
		'listPrice',
		'countdown',
		'discountedPrice',
		'button',
		'wrapperEnd',
	);

	/**
	 * The shortcode title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Pay Button', 'svbk-rcp-countdown' );
	}

	/**
	 * The shorcode UI fields.
	 *
	 * @return array
	 */
	public function fields() {

		return array(
			array(
				'label'  => esc_html__( 'Membership Level', 'svbk-rcp-countdown' ),
				'attr'   => 'membership_level',
				'type'   => 'select',
				'options' => wp_list_pluck( rcp_get_subscription_levels( 'active' ), 'name', 'id' ),
			),
			array(
				'label'    => esc_html__( 'Show Countdown', 'svbk-rcp-countdown' ),
				'attr'     => 'show_countdown',
				'type'     => 'checkbox',
			),
			array(
				'label'    => esc_html__( 'Show Countdown', 'svbk-rcp-countdown' ),
				'attr'     => 'show_discount',
				'type'     => 'checkbox',
			),
			array(
				'label'    => esc_html__( 'Payment Page', 'svbk-rcp-countdown' ),
				'attr'     => 'payment_page',
				'type'     => 'post_select',
				'query'    => array(
					'post_type' => 'page',
				),
				'multiple' => true,
			),
			array(
				'label'  => esc_html__( 'Button Label', 'svbk-rcp-countdown' ),
				'attr'   => 'button_label',
				'type'   => 'text',
			),
		);
	}

	/**
	 * Get shortcode CSS classes.
	 *
	 * @param array  $attr The shortcode attributes.
	 * @param object $subscription Optional. The subscription level object.
	 * @param object $main_discount Optional. The main discount object.
	 *
	 * @return array
	 */
	protected function getClasses( $attr, $subscription = null, $main_discount = null ) {

		$classes = parent::getClasses( $attr );

		if ( $subscription ) {
			$classes[] = 'prices';
			$classes[] = 'level-' . $subscription->role;
		}

		if ( $attr['show_discount'] && $main_discount && Discount::has_expired( $subscription->id ) ) {
			$classes[] = 'has-discount';
		}

		return $classes;
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
		$subscription = rcp_get_subscription_details( $attr['membership_level'] );

		$output = array();

		if ( ! $subscription ) {
			$output['wrapperStart'] = __( 'Membership level not found', 'svbk-rcp-countdown' );
			return $output;
		}

		Discount::trigger_expiration( $subscription );

		$main_discount = Discount::main_discount( $attr['membership_level'] );
		$full_price = rcp_get_subscription_price( $attr['membership_level'] );

		$output['wrapperStart'] = '<aside ' . self::renderClasses( $this->getClasses( $attr, $subscription, $main_discount ) ) . '>';

		if ( $attr['show_countdown'] ) :
			$output['countdown'] = '<div class="countdown level-' . esc_attr( $subscription->id ) . '" data-level="' . esc_attr( $subscription->id ) . '" >00:00:00:00</div>';
		endif;

		$output['listPrice'] = '<div class="price regular"><span class="amount">' .
								rcp_currency_filter(
									number_format( rcp_get_subscription_price( $attr['membership_level'] ), rcp_currency_decimal_filter() )
								) .
							'</span><span class="price-note">*IVA compresa</span></div>';

		if ( $attr['show_discount'] ) {

			if ( is_object( $main_discount ) ) {
				// calculate the after-discount price.
				$discounts    = new RCP_Discounts();
				$full_price = $discounts->calc_discounted_price( $full_price, $main_discount->amount, $main_discount->unit );
				$output['discountedPrice'] = '<div class="price discounted"><span class="amount">' . rcp_currency_filter( number_format( $full_price, rcp_currency_decimal_filter() ) ) . '</span><span class="price-note">*IVA compresa</span></div>';
			}

			$output['button'] = '<a href="' . get_permalink( $attr['payment_page'] ) . '" class="button" data-dc="' . esc_attr( $main_discount->code ) . '" >' . esc_html( $attr['button_label'] ) . '</a>';

		} else {
			$output['button'] = '<a href="' . get_permalink( $attr['payment_page'] ) . '" class="button" >' . esc_html( $attr['button_label'] ) . '</a>';
		}

		$output['wrapperEnd'] = '</aside>';

		return $output;

	}

}

