<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

function shipshap_init_gateway_class() {

	class Shipshap_WCPayment_Gateway extends WC_Payment_Gateway {
		public function __construct() {
			$this->id = 'shipshap_payments';
			$this->icon = plugins_url('/assets/favicon-32x32.png', __FILE__) ;
			$this->method_title = __('Shipshap Payment Methods', 'shipshap');
			$this->method_description = __('Payments via Shipshap', 'shipshap');

			$this->enabled = 'yes';
			$this->has_fields = true;

			$this->init_settings();
			$this->publishable_token = get_option('wc_shipshap_publishable_token');
			$this->url = shipshap_get_domain_url();
			$this->log = wc_get_logger();
			$this->log_context = array('source' => 'shipshap');
		}

		public function payment_fields() {
			$token = $this->publishable_token;
			$payload = array(
				'provider' => 'STRIPE',
			);
			$payment_fields_response = shipshap_make_api_call('/payments/payment-method-setup', 'POST', $payload, $token);
			if (!$payment_fields_response['success']) {
				$this->log->error('Unable to fetch payment method setup data from shipshap. Reason: ' . $payment_fields_response['reason'], $this->log_context);
				return;
			}
			$response_payload = $payment_fields_response['data'];
			$function_arguments = array(
				'clientSecret' => $response_payload['client_secret'],
				'stripePublishableToken' => $response_payload['stripe_pub_token'],
				'token' => $this->publishable_token,
			);
			echo '<div id="app-shipshap-mmmm"></div>';
			echo '<div id="shipshap-confirmation-elementid"></div>';
			$inline_javascript_string = '
				jQuery( function( $ ) {
					$("body").on(
					    "updated_checkout init_checkout",
				        function() {
				        	setTimeout(
				        			function() {
				        				shipshapCheckoutFunction(' . wp_json_encode($function_arguments) . ');
				        			},
				        			500
				        	);
				        }
                    );
				});
			';
			wp_nonce_field('payment-fields-shipshap');
			wp_enqueue_script('main-js', plugins_url('/jsapp/dist/main.js', __FILE__), array('jquery'), '0.1.0', array('in_footer' => true));
			wp_add_inline_script('main-js', $inline_javascript_string);
		}

		public function get_cart_id_from_order($order) {
			$token = $this->publishable_token;
			$domain = $this->url;
			$item_array = array();
			$order_items = $order->get_items();
			$order_id = $order->get_id();
			foreach ($order_items as $item_id => $item) {
				$product_name = $item['name'];
				$item_total = $order->get_item_meta($item_id, '_line_total', true);
				$product = $item->get_product();
				$item_array[] = array(
					'quantity' => $item->get_quantity(),
					'sku' => shipshap_get_sku_from_product($product),
				);
			}
			$payload = array(
				'items' => $item_array,
				'address_to' => array(
					'name' => $order->get_formatted_shipping_full_name(),
					'country' => $order->get_shipping_country(),
					'state' => $order->get_shipping_state(),
					'city' => $order->get_shipping_city(),
					'street1' => $order->get_shipping_address_1(),
					'street2' => $order->get_shipping_address_2(),
					'zip_code' => $order->get_shipping_postcode(),
				),
				'currency' => get_woocommerce_currency(),
				'order_id' => $order_id,
			);
			$payment_fields_response = shipshap_make_api_call('/v1/carts', 'POST', $payload, $token);
			if (!$payment_fields_response['success']) {
				$this->log->error('Unable to fetch payment method setup data from shipshap.' . $payment_fields_response['reason'], $this->log_context);
				return;
			}
			$response_payload = $payment_fields_response['data'];
			return $response_payload['id'];
		}

		public function create_cart_at_shipshap($order, $payment_method) {
			$token = $this->publishable_token;
			$domain = $this->url;
			$cart_id = $this->get_cart_id_from_order($order);
			$payload = array('customer_payment_method' => $payment_method, 'value_to_be_paid' => $order->get_total());
			$payment_request_creation_response = shipshap_make_api_call(
				'/v1/carts/' . $cart_id . '/payment-requests',
				'POST',
				$payload,
				$token
			);
			if (false === $payment_request_creation_response['success']) {
				$this->log->error('Unable to create cart.' . $payment_request_creation_response['reason'], $this->log_context);
				return null;
			}
			$payment_request_creation_response_payload = $payment_request_creation_response['data'];
			return array(
				'id' => $payment_request_creation_response_payload['id'],
				'cart_id' => $cart_id,
				'is_already_paid' => (
					'credit_card' === $payment_request_creation_response_payload['customer_payment_method']['payment_method_type'] && 
				    'complete' === $payment_request_creation_response_payload['payment']['state']
				),
			);
		}

		public function process_payment($order_id) {
			wp_verify_nonce( $_REQUEST['my_nonce'], 'payment-fields-shipshap' );
			// payment_method_identifier_shipshap
			global $woocommerce;
			if(!isset($_POST['payment_method_identifier_shipshap'])) {
				return array('result' => 'error');
			}
			
			$payment_method = sanitize_text_field($_POST['payment_method_identifier_shipshap']);
			
			if (empty($payment_method)) {
				return array('result' => 'error');
			}

			$order = new WC_Order($order_id);
			$order->update_status('on-hold', __('Awaiting shipshap confirmation', 'wc-shipshap'));
			$payment_request = $this->create_cart_at_shipshap($order, $payment_method);
			$order->update_meta_data('shipshap_payment_request_id', $payment_request['id']);
			
			if (null === $payment_request) {
				$this->log->error('Unable to process payment', $this->log_context);
				return array('result' => 'error');
			}
			if ($payment_request['is_already_paid']) {
				$order->update_status('processing', __('Order payment was instant.', 'wc-shipshap'));
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url($order),
				);
			}
			return array(
				'result' => 'success',
				'redirect' => '#shipshapdata---ABC098' . http_build_query(array(
					'id' => $payment_request['id'],
					'cartId' => $payment_request['cart_id'],
					'redirect' => $this->get_return_url($order),
					'orderId' => $order_id,
				)) . '==PPPPP',
			);
		}

	}
}

function shipshap_add_gateway_class($methods) {
	$methods[] = 'Shipshap_WCPayment_Gateway';
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'shipshap_add_gateway_class');
add_action('plugins_loaded', 'shipshap_init_gateway_class');

