<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Plugin Name: WooCommerce nowpayments.io Gateway
 * Plugin URI: https://www.nowpayments.io/
 * Description:  Provides a nowpayments.io Payment Gateway.
 * Author: nowpayments.io
 * Author URI: https://www.nowpayments.io/
 * Version: 0.1.6 beta
 */

/**
 * nowpayments.io Gateway
 * Based on the PayPal Standard Payment Gateway
 *
 * Provides a nowpayments.io Payment Gateway.
 *
 * @class 		WC_nowpayments
 * @extends		WC_Gateway_nowpayments
 * @version		0.1.8a beta
 * @package		WooCommerce/Classes/Payment
 * @author 		nowpayments.io based on PayPal module by WooThemes
 */

add_action( 'plugins_loaded', 'nowpayments_gateway_load', 0 );
function nowpayments_gateway_load() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        // oops!
        return;
    }

    /**
     * Add the gateway to WooCommerce.
     */
    add_filter( 'woocommerce_payment_gateways', 'wcnowpayments_add_gateway' );
	add_action( 'woocommerce_thankyou', 'custom_woocommerce_auto_complete_order');
	function custom_woocommerce_auto_complete_order( $order_id ) {
		if ( ! $order_id ) return;
		$order = wc_get_order( $order_id );
		$order->update_status( 'processing' );
		$order->payment_complete();
	}

    function wcnowpayments_add_gateway( $methods ) {
    	if (!in_array('WC_Gateway_nowpayments', $methods)) {
				$methods[] = 'WC_Gateway_nowpayments';
			}
			return $methods;
    }


    class WC_Gateway_nowpayments extends WC_Payment_Gateway {


    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
	public function __construct() {
		global $woocommerce;

        $this->id           = 'nowpayments';
        $this->icon         = apply_filters( 'woocommerce_nowpayments_icon', plugins_url().'/nowpayments-payment-gateway-for-woocommerce/assets/images/icons/nowpayments.png' );
        $this->has_fields   = false;
        $this->method_title = __( 'nowpayments.io', 'woocommerce' );
        

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title 			= $this->get_option( 'title' );
		$this->description 		= $this->get_option( 'description' );
		$this->api_key 			= $this->get_option( 'api_key' );
		$this->send_shipping	= $this->get_option( 'send_shipping' );
		$this->debug_email			= $this->get_option( 'debug_email' );
		$this->allow_zero_confirm = $this->get_option( 'allow_zero_confirm' ) == 'yes' ? true : false;
		$this->form_submission_method = $this->get_option( 'form_submission_method' ) == 'yes' ? true : false;
		$this->invoice_prefix	= $this->get_option( 'invoice_prefix', 'WC-' );
		$this->simple_total = $this->get_option( 'simple_total' ) == 'yes' ? true : false;

		// Logs
		$this->log = new WC_Logger();

		// Actions
		add_action( 'woocommerce_receipt_nowpayments', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( !$this->is_valid_for_use() ) $this->enabled = false;
    }


    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use() {
        //if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_nowpayments_supported_currencies', array( 'AUD', 'CAD', 'USD', 'EUR', 'JPY', 'GBP', 'CZK', 'BTC', 'LTC' ) ) ) ) return false;
        // ^- instead of trying to maintain this list just let it always work
        return true;
    }

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {

		?>
		<h3><?php _e( 'nowpayments.io', 'woocommerce' ); ?></h3>
		<p><?php _e( 'Completes checkout via nowpayments.io', 'woocommerce' ); ?></p>

    	<?php if ( $this->is_valid_for_use() ) : ?>

			<table class="form-table">
			<?php
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
			?>
			</table><!--/.form-table-->

		<?php else : ?>
            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'nowpayments.io does not support your store currency.', 'woocommerce' ); ?></p></div>
		<?php
			endif;
	}


    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields() {

    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable nowpayments.io', 'woocommerce' ),
							'default' => 'yes'
						),
			'title' => array(
							'title' => __( 'Title', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'nowpayments.io', 'woocommerce' ),
							'desc_tip'      => true,
						),
			'description' => array(
							'title' => __( 'Description', 'woocommerce' ),
							'type' => 'textarea',
							'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'Pay with Bitcoin, Litecoin, or other altcoins via nowpayments.io', 'woocommerce' )
						),
			'api_key' => array(
							'title' => __( 'Api Key', 'woocommerce' ),
							'type' 			=> 'text',
							'description' => __( 'Please enter your nowpayments.io Api Key.', 'woocommerce' ),
							'default' => '',
						),
			'simple_total' => array(
							'title' => __( 'Compatibility Mode', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( "This may be needed for compatibility with certain addons if the order total isn't correct.", 'woocommerce' ),
							'default' => ''
						),
			'send_shipping' => array(
							'title' => __( 'Collect Shipping Info?', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable Shipping Information on Checkout page', 'woocommerce' ),
							'default' => 'yes'
						),
			'invoice_prefix' => array(
							'title' => __( 'Invoice Prefix', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'Please enter a prefix for your invoice numbers. If you use your nowpayments.io account for multiple stores ensure this prefix is unique.', 'woocommerce' ),
							'default' => 'WC-',
							'desc_tip'      => true,
						),
		);

    }


	/**
	 * Get nowpayments.io Args
	 *
	 * @access public
	 * @param mixed $order
	 * @return array
	 */
	function get_nowpayments_args( $order ) {
		global $woocommerce;

		$order_id = $order->id;

		if ( in_array( $order->billing_country, array( 'US','CA' ) ) ) {
			$order->billing_phone = str_replace( array( '( ', '-', ' ', ' )', '.' ), '', $order->billing_phone );
		}

		// nowpayments.io Args
		$nowpayments_args = array(
				'cmd' 					=> '_pay_auto',
				'allow_extra' 				=> 0,
				// Get the currency from the order, not the active currency
				// NOTE: for backward compatibility with WC 2.6 and earlier,
				// $order->get_order_currency() should be used instead
				'currency' 		=> $order->get_currency(),
				'reset' 				=> 1,
				'success_url' 				=> $this->get_return_url( $order ),
				'cancel_url'			=> esc_url_raw($order->get_cancel_order_url_raw()),

				// Order key + ID
				'invoice'				=> $this->invoice_prefix . $order->get_order_number(),
				'custom' 				=> serialize( array( $order->id, $order->order_key ) ),
				'order_id'				=> $order->id,
				'api_key'           => $this->api_key,

				// Billing Address info
				'first_name'			=> $order->billing_first_name,
				'last_name'				=> $order->billing_last_name,
				'email'					=> $order->billing_email,
		);

		if ($this->send_shipping == 'yes') {
			$nowpayments_args = array_merge($nowpayments_args, array(
				'want_shipping' => 1,
				'address1'				=> $order->billing_address_1,
				'address2'				=> $order->billing_address_2,
				'city'					=> $order->billing_city,
				'state'					=> $order->billing_state,
				'zip'					=> $order->billing_postcode,
				'country'				=> $order->billing_country,
				'phone'					=> $order->billing_phone,
			));
		} else {
			$nowpayments_args['want_shipping'] = 0;
		}

		if ($this->simple_total) {
			$nowpayments_args['item_name'] 	= sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() );
			$nowpayments_args['quantity'] 		= 1;
			$nowpayments_args['amountf'] 		= number_format( $order->get_total(), 8, '.', '' );
			$nowpayments_args['taxf'] 				= 0.00;
			$nowpayments_args['shippingf']		= 0.00;
		} else if ( wc_tax_enabled() && wc_prices_include_tax() ) {
			$nowpayments_args['item_name'] 	= sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() );
			$nowpayments_args['quantity'] 		= 1;
			$nowpayments_args['amountf'] 		= number_format( $order->get_total() - $order->get_total_shipping() - $order->get_shipping_tax(), 8, '.', '' );
			$nowpayments_args['shippingf']		= number_format( $order->get_total_shipping() + $order->get_shipping_tax() , 8, '.', '' );
			$nowpayments_args['taxf'] 				= 0.00;
		} else {
			$nowpayments_args['item_name'] 	= sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() );
			$nowpayments_args['quantity'] 		= 1;
			$nowpayments_args['amountf'] 		= number_format( $order->get_total() - $order->get_total_shipping() - $order->get_total_tax(), 8, '.', '' );
			$nowpayments_args['shippingf']		= number_format( $order->get_total_shipping(), 8, '.', '' );
			$nowpayments_args['taxf']				= $order->get_total_tax();
		}
		$order_cur = wc_get_order($order_id);
		$items_cur = $order_cur->get_items();
		$items = [];
		foreach( $items_cur as $item_id => $item ){
			$items[] = $item->get_data();
		}
		$nowpayments_args["items"] = json_encode($items);
		$nowpayments_args = apply_filters( 'woocommerce_nowpayments_args', $nowpayments_args );

		return $nowpayments_args;
	}


    /**
	 * Generate the nowpayments button link
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_nowpayments_url($order) {
		global $woocommerce;

		if ( $order->status != 'completed' && get_post_meta( $order->id, 'nowpayments payment complete', true ) != 'Yes' ) {
			//$order->update_status('on-hold', 'Customer is being redirected to nowpayments...');
			$order->update_status('pending', 'Customer is being redirected to nowpayments...');
		}

		$nowpayments_adr = 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 's' : '') . '://';
		$nowpayments_adr = $nowpayments_adr . $_SERVER['SERVER_NAME'] . "/wp-content/plugins/nowpayments-payment-gateway-for-woocommerce/checkout.php?";
		$nowpayments_args = $this->get_nowpayments_args( $order );
		$nowpayments_adr .= http_build_query( $nowpayments_args, '', '&' );
		return $nowpayments_adr;
	}


    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
	function process_payment( $order_id ) {
		$file = 'people.txt';
		$current = "John Smith\n";
		file_put_contents($file, $current);
		$order          = wc_get_order( $order_id );
		return array(				
			'result' 	=> 'success',
			'redirect'	=> $this->generate_nowpayments_url($order),);

	}


    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
	function receipt_page( $order ) {
		echo '<p>'.__( 'Thank you for your order, please click the button below to pay with nowpayments.io.', 'woocommerce' ).'</p>';

		echo $this->generate_nowpayments_form( $order );
	}

	/**
	 * Successful Payment!
	 *
	 * @access public
	 * @param array $posted
	 * @return void
	 */
	function successful_request( $posted ) {
		global $woocommerce;
		$posted = stripslashes_deep( $posted );
		// Custom holds post ID
	    if (!empty($_POST['invoice']) && !empty($_POST['custom'])) {
			    $order = $this->get_nowpayments_order( $posted );
		$order->update_status('completed', 'Order has been delivered.');
		$order->payment_complete();
		$order->add_order_note( __('IPN2 payment completed', 'woocommerce') );
        	$this->log->add( 'nowpayments', 'Order #'.$order->id.' payment status: ' . $posted['status_text'] );
         	$order->add_order_note('nowpayments.io Payment Status: '.$posted['status_text']);

         	if ( $order->status != 'completed' && get_post_meta( $order->id, 'nowpayments payment complete', true ) != 'Yes' ) {
         		// no need to update status if it's already done
            if ( ! empty( $posted['txn_id'] ) )
             	update_post_meta( $order->id, 'Transaction ID', $posted['txn_id'] );
            if ( ! empty( $posted['first_name'] ) )
             	update_post_meta( $order->id, 'Payer first name', $posted['first_name'] );
            if ( ! empty( $posted['last_name'] ) )
             	update_post_meta( $order->id, 'Payer last name', $posted['last_name'] );
            if ( ! empty( $posted['email'] ) )
             	update_post_meta( $order->id, 'Payer email', $posted['email'] );

						if ($posted['status'] >= 100 || $posted['status'] == 2 || ($this->allow_zero_confirm && $posted['status'] >= 0 && $posted['received_confirms'] > 0 && $posted['received_amount'] >= $posted['amount2'])) {
							print "Marking complete\n";
							update_post_meta( $order->id, 'nowpayments payment complete', 'Yes' );
             	$order->payment_complete();
						} else if ($posted['status'] < 0) {
							print "Marking cancelled\n";
              $order->update_status('cancelled', 'nowpayments.io Payment cancelled/timed out: '.$posted['status_text']);
							mail( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s cancelled/timed out', 'woocommerce' ), $order->get_order_number() ), $posted['status_text'] );
            } else {
							print "Marking pending\n";
							$order->update_status('pending', 'nowpayments.io Payment pending: '.$posted['status_text']);
						}
	        }
	        
	    }
	}


	/**
	 * get_nowpayments_order function.
	 *
	 * @access public
	 * @param mixed $posted
	 * @return void
	 */
	function get_nowpayments_order( $posted ) {
		
		$custom = maybe_unserialize( stripslashes_deep($posted['custom']) );

    	if ( is_numeric( $custom ) ) {
	    	$order_id = (int) $custom;
	    	$order_key = $posted['invoice'];
    	} elseif( is_string( $custom ) ) {
	    	$order_id = (int) str_replace( $this->invoice_prefix, '', $custom );
	    	$order_key = $custom;
    	} else {
    		list( $order_id, $order_key ) = $custom;
		}

		$order = new WC_Order( $order_id );

		if ( ! isset( $order->id ) ) {
			// We have an invalid $order_id, probably because invoice_prefix has changed
			$order_id 	= woocommerce_get_order_id_by_order_key( $order_key );
			$order 		= new WC_Order( $order_id );
		}

		// Validate key
		if ( $order->order_key !== $order_key ) {
			return FALSE;
		}

		return $order;
	}

}

class WC_nowpayments extends WC_Gateway_nowpayments {
	public function __construct() {
		_deprecated_function( 'WC_nowpayments', '1.4', 'WC_Gateway_nowpayments' );
		parent::__construct();
	}
}
}
