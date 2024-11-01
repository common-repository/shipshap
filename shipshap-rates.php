<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function shipshap_shipping_method_init()
{
    if (!class_exists('Shipshap_ShippingMethod')) {
        class Shipshap_ShippingMethod extends WC_Shipping_Method
        {
            public function __construct()
            {
                $this->id = 'wc_shipshap_shipping'; // Id for your shipping method. Should be uunique.
                $this->method_title = __('Shipshap Shipping Methods'); // Title shown in admin
                $this->method_description = __('The following options are used to configure ShipShap', 'text-domain');

                $this->enabled = get_option('wc_shipshap_enable_rates');
                $this->title = 'Shipshap';
                $this->publishable_token = get_option('wc_shipshap_publishable_token');
                $this->server_token = get_option('wc_shipshap_server_token');
                $this->url = shipshap_get_domain_url();
                $this->domain = $this->url;
                $this->log = wc_get_logger();
                $this->log_context = array('source' => 'shipshap');
            }

            public function _possibly_send_product_to_shipshap($product)
            {
                $last_updated_at = $product->get_meta('shipshap_product_last_updated_at');
                $sku = shipshap_get_sku_from_product($product);
                // if ($last_updated_at) {
                // 	$this->log->notice("Product already registered at Shipshap", $this->log_context);
                // 	return;
                // }
                $dimensions_array = shipshap_get_dimensions_array($product);
                $token = $this->server_token;
                $dimension_unit = get_option('woocommerce_dimension_unit');
                $payload = array(
                    'sku' => $sku,
                    'title' => $product->get_title(),
                    'description' => $product->get_title(),
                    'height' => $dimensions_array['height'],
                    'width' => $dimensions_array['width'],
                    'length' => $dimensions_array['length'],
                    'weight_unit' => $dimensions_array['weight_unit'],
                    'weight' => $dimensions_array['weight'],
                    'length_unit' => $dimensions_array['length_unit'],
                    'price' => $dimension_unit,
                    'variations' => array(
                        array(
                            'title' => $product->get_title(),
                            'description' => $product->get_title(),
                            'sku' => $sku,
                            'height' => $dimensions_array['height'],
                            'width' => $dimensions_array['width'],
                            'length' => $dimensions_array['length'],
                            'weight_unit' => $dimensions_array['weight_unit'],
                            'weight' => $dimensions_array['weight'],
                            'length_unit' => $dimensions_array['length_unit'],
                            'price' => $product->get_price(),
                        ),
                    ),
                );
                $product_creation_response = shipshap_make_api_call('/v1/inventory/products', 'POST', $payload, $token);
                if (!$product_creation_response['success']) {
                    $this->log->error('Unable to create product on Shipshap.' . $product_creation_response['reason'], $this->log_context);
                    return false;
                }
                $datetime = new \DateTime();
                $value = $datetime->format(DateTime::ATOM);
                $product->update_meta_data('shipshap_product_last_updated_at', $value);
                $this->log->info('Created product on Shipshap.' . $product_creation_response['data'], $this->log_context);
                $product->save();
                return true;
            }

            /**
             * Calculate_shipping function.
             *
             * @param array $package
             * @return void
             */
            public function calculate_shipping($package = array())
            {
                if (!$package['destination']['city']) {
                    $this->log->info('Address without city. Shipshap cannot produce rates', $this->log_context);
                    return;
                }
                $token = $this->publishable_token;
                $item_array = array();
                foreach ($package['contents'] as $value) {
                    $product_id = $value['variation_id'];
                    if (0 == $product_id) {
                        $product_id = $value['product_id'];
                    }
                    $product = wc_get_product($product_id);
                    $this->_possibly_send_product_to_shipshap($product);
                    $quantity = intval($value['quantity']);
                    $sku = shipshap_get_sku_from_product($product);
                    $item_array[] = array(
                        'quantity' => $quantity,
                        'sku' => $sku,
                        'chosen_variation' => array('sku' => $sku),
                    );
                }
                $used_currency = get_woocommerce_currency();
                $weight_unit = get_option('woocommerce_weight_unit');
                $dimension_unit = get_option('woocommerce_dimension_unit');
                $payload = array(
                    'address_from' => shipshap_get_store_address_array(),
                    'address_to' => array(
                        'name' => 'Quote Address',
                        'country' => $package['destination']['country'],
                        'state' => $package['destination']['state'],
                        'city' => $package['destination']['city'],
                        'street1' => $package['destination']['address_1'],
                        'street2' => $package['destination']['address_2'],
                        'zip_code' => $package['destination']['postcode'],
                        'is_quote_only' => true,
                    ),
                    'items' => $item_array,
                    'currency' => $used_currency,
                );
                $rates_response = shipshap_make_api_call('/v1/carts?wait_for_rates=true', 'POST', $payload, $token);
                if (!$rates_response['success']) {
                    $this->log->error('Unable to get rates from Shipshap. Reason:' . $rates_response['reason'], $this->log_context);
                    return;
                }
                $response_payload = $rates_response['data'];
                $cart_id = $response_payload['id'];
                $received_rates = $response_payload['rates'];
                if ($received_rates) {
                    foreach ($received_rates as $value) {
                        if ($used_currency === $value['currency']) {
                            $this->add_rate(array(
                                'id' => $value['id'],
                                'label' => $value['service']['name'],
                                'cost' => $value['amount'],
                                'calc_tax' => 'per_item',
                                'meta_data' => array('rate_id' => $value['id'], 'cart_id' => $cart_id),
                            ));
                        } else {
                            $this->log->warning('Skipping rate because it doesnt match the store currency', $this->log_context);
                        }
                    }
                }
            }
        }
    }
}

function shipshap_add_shipping_method($methods)
{
    $methods['wc_shipshap_shipping_methods'] = 'Shipshap_ShippingMethod';
    return $methods;
}

add_action('woocommerce_shipping_init', 'shipshap_shipping_method_init');
add_filter('woocommerce_shipping_methods', 'shipshap_add_shipping_method');
