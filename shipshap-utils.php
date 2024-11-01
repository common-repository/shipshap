<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function shipshap_get_domain_url()
{
    return 'https://api.shipshap.com';
}

function shipshap_get_sku_from_product($product)
{
    $sku = $product->get_sku();
    if ($sku) {
        return $sku;
    }
    return 'product-' . $product->get_id();
}

function shipshap_get_store_address_array()
{
    $store_address = get_option('woocommerce_store_address');
    $store_address_2 = get_option('woocommerce_store_address_2');
    $store_city = get_option('woocommerce_store_city');
    $store_postcode = get_option('woocommerce_store_postcode');
    $store_raw_country = get_option('woocommerce_default_country');
    // Split the country/state
    $split_country = explode(':', $store_raw_country);
    // Country and state separated:
    $store_country = $split_country[0];
    $store_state = $split_country[1];
    return array(
        'name' => 'Quote Address',
        'country' => $store_country,
        'state' => $store_state,
        'city' => $store_city,
        'street1' => $store_address,
        'street2' => $store_address_2,
        'zip_code' => $store_postcode,
        'is_quote_only' => true,
    );
}

function shipshap_get_dimensions_array($product)
{
    $weight_unit = get_option('woocommerce_weight_unit');
    $dimension_unit = get_option('woocommerce_dimension_unit');
    $height = $product->get_height() ? $product->get_height() : get_option('wc_shipshap_default_height_on_missing');
    $width = $product->get_width() ? $product->get_width() : get_option('wc_shipshap_default_width_on_missing');
    $length = $product->get_length() ? $product->get_length() : get_option('wc_shipshap_default_length_on_missing');
    $weight = $product->get_weight() ? $product->get_weight() : get_option('wc_shipshap_default_weight_on_missing');
    return array(
        'height' => $height,
        'width' => $width,
        'length' => $length,
        'weight_unit' => $weight_unit,
        'weight' => $weight,
        'length_unit' => $dimension_unit,
    );
}

function shipshap_make_api_call($endpoint, $method, $payload, $token)
{
    $logger = wc_get_logger();
    $context = array('source' => 'shipshap');
    $domain = shipshap_get_domain_url();
    $response = wp_remote_post($domain . $endpoint, array(
        'method' => $method,
        'body' => wp_json_encode($payload),
        'headers' => array('Authorization' => 'PublicToken ' . $token, 'Content-Type' => 'application/json'),
    ));
    $response_json = wp_remote_retrieve_body($response);
    if (is_wp_error($response)) {
        $logger->error('Unable to reach Shipshap', $context);
        return array(
            'success' => false,
            'reason' => 'Unable to reach Shipshap',
        );
    }
    $code = $response['response']['code'];
    if ($code >= 300) {
        $logger->error('Got an error message from Shipshap (' . $code . '): ' . $response_json, $context);
        return array(
            'success' => false,
            'reason' => 'Got bad status code ' . $code,
        );
    }
    $response_payload = json_decode($response_json, true);
    $logger->debug("Communication with Shipshap successful on '" . $endpoint . "'", $context);
    return array(
        'success' => true,
        'data' => $response_payload,
    );
}

