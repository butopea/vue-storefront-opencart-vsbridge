<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeOrder extends VsbridgeController{

    private $error = array();

    /*
     * POST /vsbridge/order/create
     * Queue the order into the order queue which will be asynchronously submitted to the eCommerce backend.
     *
     * REQUEST BODY:
     * The user_id field is a numeric user id as returned in api/user/me.
     * The cart_id is a guest or authorized users quote id (You can mix guest cart with authorized user as well).
     *
     * {
     *      "user_id": "",
     *      "cart_id": "d90e9869fbfe3357281a67e3717e3524",
     *      "products": [
     *          {
     *              "sku": "WT08-XS-Yellow",
     *              "qty": 1
     *          }
     *      ],
     *      "addressInformation": {
     *          "shippingAddress": {
     *              "region": "",
     *              "region_id": 0,
     *              "country_id": "PL",
     *              "street": [
     *                  "Example",
     *                  "12"
     *              ],
     *              "company": "NA",
     *              "telephone": "",
     *              "postcode": "50-201",
     *              "city": "Wroclaw",
     *              "firstname": "Piotr",
     *              "lastname": "Karwatka",
     *              "email": "pkarwatka30@divante.pl",
     *              "region_code": ""
     *          },
     *          "billingAddress": {
     *              "region": "",
     *              "region_id": 0,
     *              "country_id": "PL",
     *              "street": [
     *                  "Example",
     *                  "12"
     *              ],
     *              "company": "Company name",
     *              "telephone": "",
     *              "postcode": "50-201",
     *              "city": "Wroclaw",
     *              "firstname": "Piotr",
     *              "lastname": "Karwatka",
     *              "email": "pkarwatka30@divante.pl",
     *              "region_code": "",
     *              "vat_id": "PL88182881112"
     *          },
     *          "shipping_method_code": "flatrate",
     *          "shipping_carrier_code": "flatrate",
     *          "payment_method_code": "cashondelivery",
     *          "payment_method_additional": {}
     *      },
     *      "order_id": "1522811662622-d3736c94-49a5-cd34-724c-87a3a57c2750",
     *      "transmited": false,
     *      "created_at": "2018-04-04T03:14:22.622Z",
     *      "updated_at": "2018-04-04T03:14:22.622Z"
     *  }
     *
     * Notes: We're currently only using cart_id to retrieve the user information since it's insecure to rely on a user-provided user_id.
     * TODO: Talk with VSF core team to replace user_id with an optional token parameter.
     * TODO: Since we store most of the user/guest information in the session, we won't check some of the input parameters. Check if offline mode works this way.
     */

    public function create(){
        $input = $this->getPost();

        if(!empty($input['cart_id'])){

            if($this->validateCartId($input['cart_id'], null)) {

                /* Following the logic from catalog/controller/checkout/confirm */

                $this->load->language('vsbridge/api');

                $this->load->model('vsbridge/api');
                $this->load->model('localisation/country');
                $this->load->model('localisation/zone');

                if ($this->cart->hasShipping()) {
                    // Validate if shipping address has been set, and copy it to the session info and save if the user is logged in
                    if (!empty($input['addressInformation']['shippingAddress'])) {

                        $shipping_address = array(
                            'firstname'         => $this->checkInput($input['addressInformation']['shippingAddress'], 'firstname', true, false),
                            'lastname'          => $this->checkInput($input['addressInformation']['shippingAddress'], 'lastname', true, false),
                            'company'           => $this->checkInput($input['addressInformation']['shippingAddress'], 'company', false, true, ''),
                            'address_1'         => implode(' ', $this->checkInput($input['addressInformation']['shippingAddress'], 'street', true, false)),
                            'address_2'         => '',
                            'postcode'          => $this->checkInput($input['addressInformation']['shippingAddress'], 'postcode', true, false),
                            'city'              => $this->checkInput($input['addressInformation']['shippingAddress'], 'city', true, false)
                        );

                        if($zone_id = $this->model_vsbridge_api->getZoneIdFromName($this->checkInput($input['addressInformation']['shippingAddress'], 'region', true, false))){
                            $shipping_address['zone_id'] = (int) $zone_id;
                        }else{
                            $shipping_address['zone_id'] = $this->config->get('config_zone_id');
                        }

                        if($country_id = $this->model_vsbridge_api->getCountryIdFromCode($this->checkInput($input['addressInformation']['shippingAddress'], 'country_id', true, false))){
                            $shipping_address['country_id'] = (int) $country_id;
                        }else{
                            $shipping_address['country_id'] = $this->config->get('config_zone_id');
                        }

                        $country_data = $this->model_localisation_country->getCountry($shipping_address['country_id']);
                        $zone_data = $this->model_localisation_zone->getZone($shipping_address['zone_id']);

                        $shipping_address['zone'] = (isset($zone_data['name'])) ? $zone_data['name'] : '';
                        $shipping_address['zone_code'] = (isset($zone_data['code'])) ? $zone_data['code'] : '';
                        $shipping_address['country'] = (isset($country_data['name'])) ? $country_data['name'] : '';
                        $shipping_address['iso_code_2'] = (isset($country_data['iso_code_2'])) ? $country_data['iso_code_2'] : '';
                        $shipping_address['iso_code_3'] = (isset($country_data['iso_code_3'])) ? $country_data['iso_code_3'] : '';
                        $shipping_address['address_format'] = (isset($country_data['address_format'])) ? $country_data['address_format'] : '';

                        $this->session->data['shipping_address'] = $shipping_address;

                    }else{
                        $this->error[] = $this->language->get('error_no_shipping_address');
                    }

                    // Validate if shipping method has been set.
                    if (!isset($this->session->data['shipping_method'])) {
                        $this->error[] = $this->language->get('error_no_shipping_method');
                    }
                } else {
                    unset($this->session->data['shipping_address']);
                    unset($this->session->data['shipping_method']);
                    unset($this->session->data['shipping_methods']);
                }

                // Validate if payment address has been set.
                if (!empty($input['addressInformation']['billingAddress'])) {

                    $payment_address = array(
                        'firstname'         => $this->checkInput($input['addressInformation']['billingAddress'], 'firstname', true, false),
                        'lastname'          => $this->checkInput($input['addressInformation']['billingAddress'], 'lastname', true, false),
                        'company'           => $this->checkInput($input['addressInformation']['billingAddress'], 'company', false, true, ''),
                        'address_1'         => implode(' ', $this->checkInput($input['addressInformation']['billingAddress'], 'street', true, false)),
                        'address_2'         => '',
                        'postcode'          => $this->checkInput($input['addressInformation']['billingAddress'], 'postcode', true, false),
                        'city'              => $this->checkInput($input['addressInformation']['billingAddress'], 'city', true, false)
                    );

                    if($zone_id = $this->model_vsbridge_api->getZoneIdFromName($this->checkInput($input['addressInformation']['billingAddress'], 'region', true, false))){
                        $payment_address['zone_id'] = (int) $zone_id;
                    }else{
                        $payment_address['zone_id'] = $this->config->get('config_zone_id');
                    }

                    if($country_id = $this->model_vsbridge_api->getCountryIdFromCode($this->checkInput($input['addressInformation']['billingAddress'], 'country_id', true, false))){
                        $payment_address['country_id'] = (int) $country_id;
                    }else{
                        $payment_address['country_id'] = $this->config->get('config_zone_id');
                    }

                    $country_data = $this->model_localisation_country->getCountry($payment_address['country_id']);
                    $zone_data = $this->model_localisation_zone->getZone($payment_address['zone_id']);

                    $payment_address['zone'] = (isset($zone_data['name'])) ? $zone_data['name'] : '';
                    $payment_address['zone_code'] = (isset($zone_data['code'])) ? $zone_data['code'] : '';
                    $payment_address['country'] = (isset($country_data['name'])) ? $country_data['name'] : '';
                    $payment_address['iso_code_2'] = (isset($country_data['iso_code_2'])) ? $country_data['iso_code_2'] : '';
                    $payment_address['iso_code_3'] = (isset($country_data['iso_code_3'])) ? $country_data['iso_code_3'] : '';
                    $payment_address['address_format'] = (isset($country_data['address_format'])) ? $country_data['address_format'] : '';

                    $this->session->data['payment_address'] = $payment_address;
                }else{
                    $this->error[] = $this->language->get('error_no_payment_address');
                }

                // Validate if payment method has been set.
                // Since the payment method is sent via order/create in VSF, we won't check the session.
                if (isset($input['addressInformation']['payment_method_code'])) {
                    $payment_methods = $this->model_extension_extension->getExtensions('payment');

                    foreach($payment_methods as $payment_method){
                        if($payment_method['code'] == $input['addressInformation']['payment_method_code']){
                            $this->session->data['payment_method']['title'] = '';
                            $this->session->data['payment_method']['code'] = $input['addressInformation']['payment_method_code'];
                            $this->load->language('extension/payment/' . $payment_method['code']);
                            $payment_title = $this->language->get('text_title');

                            if($payment_title != 'text_title'){
                                $this->session->data['payment_method']['title'] = $payment_title;
                            }
                        }
                    }

                    if(empty($this->session->data['payment_method']['code'])){
                        $this->error[] = $this->language->get('error_invalid_payment_code');
                    }
                }else{
                    $this->error[] = $this->language->get('error_no_payment_method');
                }

                // Validate cart has products and has stock.
                if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
                    $this->error[] = $this->language->get('error_cart_product_stock');
                }

                // Validate minimum quantity requirements.
                $products = $this->cart->getProducts();

                foreach ($products as $product) {
                    $product_total = 0;

                    foreach ($products as $product_2) {
                        if ($product_2['product_id'] == $product['product_id']) {
                            $product_total += $product_2['quantity'];
                        }
                    }

                    if ($product['minimum'] > $product_total) {
                        $this->error[] = sprintf($this->language->get('error_minimum_product_quantity'), $product['minimum'], $product['model'], $product_total);

                        break;
                    }
                }

                if(empty($this->error)){

                    $order_data = array();

                    $totals = array();
                    $taxes = $this->cart->getTaxes();
                    $total = 0;

                    // Because __call can not keep var references so we put them into an array.
                    $total_data = array(
                        'totals' => &$totals,
                        'taxes'  => &$taxes,
                        'total'  => &$total
                    );

                    $this->load->model('extension/extension');

                    $sort_order = array();

                    $results = $this->model_extension_extension->getExtensions('total');

                    foreach ($results as $key => $value) {
                        $sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
                    }

                    array_multisort($sort_order, SORT_ASC, $results);

                    foreach ($results as $result) {
                        if ($this->config->get($result['code'] . '_status')) {
                            $this->load->model('extension/total/' . $result['code']);

                            // We have to put the totals in an array so that they pass by reference.
                            $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
                        }
                    }

                    $sort_order = array();

                    foreach ($totals as $key => $value) {
                        $sort_order[$key] = $value['sort_order'];
                    }

                    array_multisort($sort_order, SORT_ASC, $totals);

                    $order_data['totals'] = $totals;

                    $this->load->language('checkout/checkout');

                    $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
                    $order_data['store_id'] = $this->config->get('config_store_id');
                    $order_data['store_name'] = $this->config->get('config_name');

                    if ($order_data['store_id']) {
                        $order_data['store_url'] = $this->config->get('config_url');
                    } else {
                        if ($this->request->server['HTTPS']) {
                            $order_data['store_url'] = HTTPS_SERVER;
                        } else {
                            $order_data['store_url'] = HTTP_SERVER;
                        }
                    }

                    if ($this->customer->isLogged()) {
                        $this->load->model('account/customer');

                        $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

                        $order_data['customer_id'] = $this->customer->getId();
                        $order_data['customer_group_id'] = $customer_info['customer_group_id'];
                        $order_data['firstname'] = $customer_info['firstname'];
                        $order_data['lastname'] = $customer_info['lastname'];
                        $order_data['email'] = $customer_info['email'];
                        $order_data['telephone'] = $customer_info['telephone'];
                        $order_data['fax'] = $customer_info['fax'];
                        $order_data['custom_field'] = json_decode($customer_info['custom_field'], true);
                    } else {
                        $order_data['customer_id'] = 0;
                        $order_data['customer_group_id'] = $this->config->get('config_customer_group_id');
                        $order_data['firstname'] = $this->session->data['shipping_address']['firstname'];
                        $order_data['lastname'] = $this->session->data['shipping_address']['lastname'];
                        $order_data['email'] = $this->checkInput($input['addressInformation']['shippingAddress'], 'email', true, false);
                        $order_data['telephone'] = $this->checkInput($input['addressInformation']['shippingAddress'], 'telephone', false, true);
                        $order_data['fax'] = '';
                        $order_data['custom_field'] = array();
                    }

                    $order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
                    $order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
                    $order_data['payment_company'] = $this->session->data['payment_address']['company'];
                    $order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
                    $order_data['payment_address_2'] = $this->session->data['payment_address']['address_2'];
                    $order_data['payment_city'] = $this->session->data['payment_address']['city'];
                    $order_data['payment_postcode'] = $this->session->data['payment_address']['postcode'];
                    $order_data['payment_zone'] = $this->session->data['payment_address']['zone'];
                    $order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
                    $order_data['payment_country'] = $this->session->data['payment_address']['country'];
                    $order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
                    $order_data['payment_address_format'] = $this->session->data['payment_address']['address_format'];
                    $order_data['payment_custom_field'] = (isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array());

                    if (isset($this->session->data['payment_method']['title'])) {
                        $order_data['payment_method'] = $this->session->data['payment_method']['title'];
                    } else {
                        $order_data['payment_method'] = '';
                    }

                    if (isset($this->session->data['payment_method']['code'])) {
                        $order_data['payment_code'] = $this->session->data['payment_method']['code'];
                    } else {
                        $order_data['payment_code'] = '';
                    }

                    if ($this->cart->hasShipping()) {
                        $order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
                        $order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
                        $order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
                        $order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
                        $order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
                        $order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
                        $order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
                        $order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
                        $order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
                        $order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
                        $order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
                        $order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
                        $order_data['shipping_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array());

                        if (isset($this->session->data['shipping_method']['title'])) {
                            $order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
                        } else {
                            $order_data['shipping_method'] = '';
                        }

                        if (isset($this->session->data['shipping_method']['code'])) {
                            $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
                        } else {
                            $order_data['shipping_code'] = '';
                        }
                    } else {
                        $order_data['shipping_firstname'] = '';
                        $order_data['shipping_lastname'] = '';
                        $order_data['shipping_company'] = '';
                        $order_data['shipping_address_1'] = '';
                        $order_data['shipping_address_2'] = '';
                        $order_data['shipping_city'] = '';
                        $order_data['shipping_postcode'] = '';
                        $order_data['shipping_zone'] = '';
                        $order_data['shipping_zone_id'] = '';
                        $order_data['shipping_country'] = '';
                        $order_data['shipping_country_id'] = '';
                        $order_data['shipping_address_format'] = '';
                        $order_data['shipping_custom_field'] = array();
                        $order_data['shipping_method'] = '';
                        $order_data['shipping_code'] = '';
                    }

                    $order_data['products'] = array();

                    foreach ($this->cart->getProducts() as $product) {
                        $option_data = array();

                        foreach ($product['option'] as $option) {
                            $option_data[] = array(
                                'product_option_id'       => $option['product_option_id'],
                                'product_option_value_id' => $option['product_option_value_id'],
                                'option_id'               => $option['option_id'],
                                'option_value_id'         => $option['option_value_id'],
                                'name'                    => $option['name'],
                                'value'                   => $option['value'],
                                'type'                    => $option['type']
                            );
                        }

                        $order_data['products'][] = array(
                            'product_id'  => $product['product_id'],
                            'name'        => $product['name'],
                            'base_price'  => $product['base_price'],
                            'cost'        => $product['cost'],
                            'supplier_id' => $product['supplier_id'],
                            'model'       => $product['model'],
                            'option'      => $option_data,
                            'download'    => $product['download'],
                            'quantity'    => $product['quantity'],
                            'subtract'    => $product['subtract'],
                            'price'       => $product['price'],
                            'total'       => $product['total'],
                            'tax'         => $this->tax->getTax($product['price'], $product['tax_class_id']),
                            'reward'      => $product['reward']
                        );
                    }

                    // Gift Voucher
                    $order_data['vouchers'] = array();

                    if (!empty($this->session->data['vouchers'])) {
                        foreach ($this->session->data['vouchers'] as $voucher) {
                            $order_data['vouchers'][] = array(
                                'description'      => $voucher['description'],
                                'code'             => token(10),
                                'to_name'          => $voucher['to_name'],
                                'to_email'         => $voucher['to_email'],
                                'from_name'        => $voucher['from_name'],
                                'from_email'       => $voucher['from_email'],
                                'voucher_theme_id' => $voucher['voucher_theme_id'],
                                'message'          => $voucher['message'],
                                'amount'           => $voucher['amount']
                            );
                        }
                    }

                    $vat_id = $this->checkInput($input['addressInformation']['billingAddress'], 'vat_id', false, true, null);

                    $order_data['comment'] = $vat_id ? 'VAT ID: '.$vat_id : ''; // not implemented yet - we store the VAT ID here if provided
                    $order_data['total'] = $total_data['total'];

                    if (isset($this->request->cookie['tracking'])) {
                        $order_data['tracking'] = $this->request->cookie['tracking'];

                        $subtotal = $this->cart->getSubTotal();

                        // Affiliate
                        $this->load->model('affiliate/affiliate');

                        $affiliate_info = $this->model_affiliate_affiliate->getAffiliateByCode($this->request->cookie['tracking']);

                        if ($affiliate_info) {
                            $order_data['affiliate_id'] = $affiliate_info['affiliate_id'];
                            $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
                        } else {
                            $order_data['affiliate_id'] = 0;
                            $order_data['commission'] = 0;
                        }

                        // Marketing
                        $this->load->model('checkout/marketing');

                        $marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

                        if ($marketing_info) {
                            $order_data['marketing_id'] = $marketing_info['marketing_id'];
                        } else {
                            $order_data['marketing_id'] = 0;
                        }
                    } else {
                        $order_data['affiliate_id'] = 0;
                        $order_data['commission'] = 0;
                        $order_data['marketing_id'] = 0;
                        $order_data['tracking'] = '';
                    }

                    $order_data['language_id'] = $this->config->get('config_language_id');
                    $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
                    $order_data['currency_code'] = $this->session->data['currency'];
                    $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
                    $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

                    // Fixing some PHP notice errors due to OCmods
                    $order_data['payment_cost'] = '';
                    $order_data['shipping_cost'] = '';
                    $order_data['extra_cost'] = '';

                    if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
                        $order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
                    } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
                        $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
                    } else {
                        $order_data['forwarded_ip'] = '';
                    }

                    if (isset($this->request->server['HTTP_USER_AGENT'])) {
                        $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
                    } else {
                        $order_data['user_agent'] = '';
                    }

                    if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
                        $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
                    } else {
                        $order_data['accept_language'] = '';
                    }

                    $this->load->model('checkout/order');

                    $order_id = $this->model_checkout_order->addOrder($order_data);

                    $this->session->data['order_id'] = $order_id;

                    $order_status_id = $this->config->get('config_order_status_id');

                    $this->model_checkout_order->addOrderHistory($order_id, $order_status_id);

                    // clear cart since the order has already been successfully stored.
                    $this->cart->clear();

                    // clear session data
                    unset($this->session->data['shipping_method']);
                    unset($this->session->data['shipping_methods']);
                    unset($this->session->data['payment_method']);
                    unset($this->session->data['payment_methods']);
                    unset($this->session->data['guest']);
                    unset($this->session->data['comment']);
                    unset($this->session->data['order_id']);
                    unset($this->session->data['coupon']);
                    unset($this->session->data['reward']);
                    unset($this->session->data['voucher']);
                    unset($this->session->data['vouchers']);
                    unset($this->session->data['totals']);

                    $this->result = array(
                        'order_id' => $order_id
                    );

                }else{
                    $this->code = 500;
                    $this->result = implode("\n", $this->error);
                }

            }

        }else{
            $this->load->language('vsbridge/api');
            $this->code = 500;
            $this->result = $this->language->get('error_missing_input').'cart_id';
        }

        $this->sendResponse();
    }
}

