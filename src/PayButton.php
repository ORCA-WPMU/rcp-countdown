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
	 * Apply or remove taxes, to remove taxes use -1.22 (-22%).
	 *
	 * @access public
	 * @var float $taxes 
	 */
	public $tax_rate = 1.22;
	
	/**
	 * Append a string after price.
	 *
	 * @access public
	 * @var string $tax_note 
	 */
	public $price_note = '';	

	/**
	 * The template for the countdown.
	 *
	 * @access public
	 * @var string $countdown_template 
	 */
	public $countdown_template = '%D:%H:%M:%S';


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
		'show_countdown' => false,
		'show_discount' => false,
		'price_prefix' => 'only' ,
		'before_discount_prefix' => 'instead of',
		'after_discount_prefix' => 'only',		
		'price_description' => '',
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
		'wrapperBegin',
		'content',		
		'countdown',
		'buttonBegin',		
		'label',
		'regularPrice',
		'discountedPrice',
		'buttonEnd',
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
			'membership_level' => array(
				'label'  => esc_html__( 'Membership Level', 'svbk-rcp-countdown' ),
				'attr'   => 'membership_level',
				'type'   => 'select',
				'options' => wp_list_pluck( rcp_get_subscription_levels( 'active' ), 'name', 'id' ),
			),
			'price_prefix' => array(
				'label'    => esc_html__( 'Price Prefix', 'svbk-rcp-countdown' ),
				'attr'     => 'price_prefix',
				'type'     => 'text',
			),
			'show_countdown' => array(
				'label'    => esc_html__( 'Show Countdown', 'svbk-rcp-countdown' ),
				'attr'     => 'show_countdown',
				'type'     => 'checkbox',
			),			
			'show_discount' => array(
				'label'    => esc_html__( 'Show Discount', 'svbk-rcp-countdown' ),
				'attr'     => 'show_discount',
				'type'     => 'checkbox',
			),			
			'before_discount_prefix' => array(
				'label'    => esc_html__( 'Price Before Discount Prefix', 'svbk-rcp-countdown' ),
				'attr'     => 'before_discount_prefix',
				'type'     => 'text',
			),			
			'after_discount_prefix' => array(
				'label'    => esc_html__( 'Price After Discount Prefix', 'svbk-rcp-countdown' ),
				'attr'     => 'after_discount_prefix',
				'type'     => 'text',
			),			
			'payment_page' => array(
				'label'    => esc_html__( 'Payment Page', 'svbk-rcp-countdown' ),
				'attr'     => 'payment_page',
				'type'     => 'post_select',
				'query'    => array(
					'post_type' => 'page',
				),
				'multiple' => false,
			),
			'button_label' => array(
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

		$discounts = new RCP_Discounts();

		if ( $subscription ) {
			$classes[] = 'prices';
			$classes[] = 'level-' . $subscription->id;
		}

		if ( filter_var($attr['show_discount'], FILTER_VALIDATE_BOOLEAN) && $main_discount && ! $discounts->is_expired( $main_discount->id ) ) {
			$classes[] = 'has-discount';
			
			if (filter_var($attr['show_countdown'], FILTER_VALIDATE_BOOLEAN) ) {
				$classes[] = 'has-countdown';
			}			
			
		}

		return $classes;
	}

	public function priceTemplate( $classes, $price, $prefix = '', $tag = 'span' ){
		
		$output = '<span ' . self::renderClasses( $classes ) . '>';
		
		if( $prefix ) {
			$output .= '<span class="price-prefix">' . $prefix . '</span> ';
		}
		
		$output .= '<' . $tag . ' class="price-amount">' . rcp_currency_filter( $price / $this->tax_rate ) . '</' . $tag . '>';
		
		if( $this->price_note ) {
			$output .= '<span class="price-note">' . $this->price_note .'</span>';
		}
		
		$output .= '</span>';
		
		return 	$output;
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

		$output = parent::renderOutput( $attr, $content, $shortcode_tag );

		if ( ! $subscription ) {
			$output['wrapperStart'] = __( 'Membership level not found', 'svbk-rcp-countdown' );
			return $output;
		}

		$discounts = new RCP_Discounts();

		$main_discount = main_discount( $attr['membership_level'] );
		$full_price = rcp_get_subscription_price( $attr['membership_level'] );

		$output['wrapperBegin'] = '<div ' . self::renderClasses( $this->getClasses( $attr, $subscription, $main_discount ) ) . '>';
		
		$permalink = get_permalink( $attr['payment_page'] );

		if ( $attr['button_label'] ) {
			$output['label'] =  '<span class="label" >' . esc_html( $attr['button_label'] ) . '</span>';
		}
		
		if( $permalink ) {
			$output['buttonBegin'] = '<a class="button" href="' . esc_url($permalink) . '" >';
			$output['buttonEnd'] = '</a>';
		} else {
			$output['label'] = __('ERROR: Page not found', 'svbk-rcp-countdown');
		}

		$regularPrefix = $attr['price_prefix'];
		$regularClasses = array( 'price', 'regular' );
		$regularTag = 'span';
		
		if ( filter_var($attr['show_discount'], FILTER_VALIDATE_BOOLEAN) && is_object( $main_discount ) && ! $discounts->is_expired( $main_discount->id ) ) {
			$discount_price = $discounts->calc_discounted_price( $full_price, $main_discount->amount, $main_discount->unit );
			
			$expiration = $discounts->get_expiration( $main_discount->id );
			
			if ( filter_var($attr['show_countdown'], FILTER_VALIDATE_BOOLEAN) && $expiration) {

				$now = new DateTime();
				$expire = new DateTime( $expiration ); 
				$remaining = $now->diff( $expire ?: $now ); 
				
				$output['countdown'] = '<div 
					class="countdown level-' . esc_attr( $subscription->id ) . '" 
					data-level="' . esc_attr( $subscription->id ) . '"
					data-template="' . esc_attr( $this->countdown_template ) . '"
					>' . $remaining->format( $this->countdown_template ) .  '</div>';
					
			} 
			
			$output['discountedPrice'] = $this->priceTemplate( array( 'price', 'after-discount' ), $discount_price, $attr['after_discount_prefix'] );
			
			$regularClasses[] = 'before-discount';
			
			if( $regularPrefix ) {
				$regularPrefix = '<span class="without-discount" >' . $regularPrefix . '</span>';
			}			
			
			if( $attr['before_discount_prefix'] ){
				$regularPrefix = '<span class="with-discount" >' . $attr['before_discount_prefix'] . '</span>&nbsp;' .$regularPrefix  ;
			}
			
			$regularTag = 'del';
		}
		
		$output['regularPrice'] = $this->priceTemplate( $regularClasses, $full_price, $regularPrefix, $regularTag );
		$output['buttonEnd'] = '</a>';
		$output['wrapperEnd'] = '</div>';

		return $output;

	}

}

