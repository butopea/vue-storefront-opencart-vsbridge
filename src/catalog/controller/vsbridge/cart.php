<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeCart extends VsbridgeController{

    public function __construct($registry){
        parent::__construct($registry);

        $function_name = str_replace('vsbridge/cart/', '', $_REQUEST['route']);

        switch($function_name){
            case 'apply-coupon':
                $this->apply_coupon();
                break;
            case 'delete-coupon':
                $this->delete_coupon();
                break;
            case 'payment-methods':
                $this->payment_methods();
                break;
            case 'shipping-methods':
                $this->shipping_methods();
                break;
            case 'shipping-information':
                $this->shipping_information();
                break;
            case 'collect-totals':
                $this->collect_totals();
                break;
        }
    }

    /*
     * POST /vsbridge/cart/create
     * This method is used to get all the products from the backend.
     *
     * WHEN:
     * This method is called when new Vue Storefront shopping cart is created.
     * First visit, page refresh, after user-authorization ...
     * If the token GET parameter is provided it's called as logged-in user; if not - it's called as guest-user.
     * To draw the difference - let's keep to Magento example.
     * For guest user vue-storefront-api is subsequently operating on /guest-carts API endpoints and for authorized users on /carts/ endpoints.
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     *
     * Note: the cartId for authorized customers is NOT an integer due to OpenCart architecture. If it causes a problem in the future, create a table and map session_ids to integers row ids.
     */

    public function create(){
        $token = $this->getParam('token', true);

        /*
         * Generate a new session_id (just a value for the column, not a real session) to be inserted in the cart table.
         * The session_ids are marked with a vs_ prefix to be distinguished from real OpenCart sessions.
         * We can't use the API token because it expires and changes.
         *
         * For authenticated users, if there's a row in the cart table matching the customer_id, we will retrieve the saved session_id and not create a new one.
        */

        if(!empty($token) && $customer_info = $this->validateCustomerToken($token)){
            /* Authenticated customer */
            $this->load->model('vsbridge/api');
            $cart_id = $this->getSessionId($customer_info['customer_id']);
        }else{
            /* Guest */
            $cart_id = $this->getSessionId();
        }

        $this->result = $cart_id;

        $this->sendResponse();
    }

    /*
     * GET /vsbridge/cart/pull
     * Method used to fetch the current server side shopping cart content, used mostly for synchronization purposes when config.cart.synchronize=true
     *
     * WHEN:
     * This method is called just after any Vue Storefront cart modification to check if the server or client shopping cart items need to be updated.
     * It gets the current list of the shopping cart items. The synchronization algorithm in VueStorefront determines if server or client items need to be updated and executes api/cart/update or api/cart/delete accordngly.
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
     *
     * Note: Currently, all our products are non-configurable so the product_type is set manually to simple.
     *       Remember to change it here too if we implement product options in the importer.
     */

    public function pull(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');

        $this->load->model('vsbridge/api');

        if($this->validateCartId($cart_id, $token)){
            $cart = $this->cart->getProducts();

            $response = array();

            $out_of_stock_products = array();

            /* If the cart exists and has items, retrieve it */
            if(!empty($cart)){
                foreach($cart as $cart_product){
                    if(isset($cart_product['product_id']) && isset($cart_product['quantity'])){
                        $product_info = $this->model_vsbridge_api->getProductDetails($cart_product['product_id'], $this->language_id);
                        if (!$cart_product['stock']) {
                            $out_of_stock_products[] = $product_info['model'];
                        }
                        $response[] = array(
                            'item_id' => (int)$cart_product['cart_id'],
                            'sku' => $product_info['sku'],
                            'model' => $product_info['model'],
                            'qty' => (int)$cart_product['quantity'],
                            'name' => $product_info['name'],
                            'price' => (float)$product_info['price'],
                            'product_type' => 'simple',
                            'quote_id' => $cart_id
                        );
                    }
                }
            }

            if(!empty($out_of_stock_products)) {
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_out_of_stock') . ' ' . implode(', ', $out_of_stock_products);
                $this->sendResponse();
            }

            $this->result = $response;
        }

        $this->sendResponse();
    }

    /*
     * POST /vsbridge/cart/update
     * Method used to add or update shopping cart item's server side.
     * As a request body there should be JSON given representing the cart item, sku, and qty are the two required options.
     * If you like to update/edit server cart item you need to pass item_id (cart_id in OpenCart) identifier as well (can be optainted from api/cart/pull).
     *
     * WHEN:
     * This method is called just after api/cart/pull as a consequence of the synchronization process.
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
     *
     * REQUEST BODY:
     *
     * "cartItem":{
     *    "sku":"WS12-XS-Orange",
     *    "qty":1,
     *    "product_option":{
     *       "extension_attributes":{
     *          "custom_options":[
     *
     *          ],
     *          "configurable_item_options":[
     *             {
     *                "option_id":"93",
     *                "option_value":"56"
     *             },
     *             {
     *                "option_id":"142",
     *                "option_value":"167"
     *             }
     *          ],
     *          "bundle_options":[
     *
     *          ]
     *       }
     *    },
     *    "quoteId":"0a8109552020cc80c99c54ad13ef5d5a"
     *  }
     *
     * Note:
     *   quoteId is specific to magento and is the same as the cartId. We currently don't perform any checks on it.
     *   We haven't implemented product_option due to products being of type 'single'.
     */

    public function update(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');
        $input = $this->getPost();

        $this->load->model('vsbridge/api');

        if($this->validateCartId($cart_id, $token)){
            if(empty($input['cartItem'])) {
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_missing_cart_item');
                $this->sendResponse();
            }

            if(empty($input['cartItem']['sku'])){
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_missing_sku_qty');
                $this->sendResponse();
            }

            $input['cartItem']['qty'] = (int) $input['cartItem']['qty'];

            /* quantity must be greater than or equal to 1 */
            /* the delete endpoint is used for removing the item from the cart */
            if($input['cartItem']['qty'] < 1){
                $input['cartItem']['qty'] = 1;
            }

            $product_info = $this->model_vsbridge_api->getProductBySku($input['cartItem']['sku'], $this->language_id);

            if(empty($product_info)){
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_invalid_sku');
                $this->sendResponse();
            }

            // Check if the requested quantity is available on the selected product
            if(intval($input['cartItem']['qty']) > intval($product_info['quantity'])){
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_out_of_stock').' ['.$product_info['model'].'] '.$product_info['name'];
                $this->sendResponse();
            }

            // Check if the requested quantity meets the minimum (and multiple of minimum) product quantity
            if($product_info['minimum']){
                if (intval($input['cartItem']['qty']) < intval($product_info['minimum'])) {
                    $this->load->language('vsbridge/api');
                    $this->code = 500;
                    $this->result = sprintf($this->language->get('error_minimum_product_quantity'), $product_info['model'], $product_info['minimum']);
                    $this->sendResponse();
                }

                $qty_multiple = intval($input['cartItem']['qty']) / intval($product_info['minimum']);
                if (!is_int($qty_multiple)) {
                    $this->load->language('vsbridge/api');
                    $this->code = 500;
                    $this->result = sprintf($this->language->get('error_minimum_multiple_product_quantity'), $product_info['model'], $product_info['minimum']);
                    $this->sendResponse();
                }
            }

            if(isset($input['cartItem']['item_id'])){
                $this->cart->update($input['cartItem']['item_id'], $input['cartItem']['qty']);
            }else{
                $this->cart->add($product_info['product_id'], $input['cartItem']['qty']);
            }

            $cart_products = $this->cart->getProducts();

            $response = array();

            foreach($cart_products as $cart_product){
                if($cart_product['product_id'] == $product_info['product_id']){
                    $response = array(
                        'item_id' => (int) $cart_product['cart_id'],
                        'sku' => $cart_product['model'],
                        'qty' => (int) $cart_product['quantity'],
                        'name' => $cart_product['name'],
                        'price' => (float) $cart_product['price'],
                        'product_type' => 'simple',
                        'quote_id' => $cart_id
                    );
                }
            }

            $this->result = $response;
        }

        $this->sendResponse();
    }


    /*
     * POST /vsbridge/cart/delete
     * This method is used to remove the shopping cart item on server side.
     *
     * WHEN:
     * This method is called just after api/cart/pull as a consequence of the synchronization process
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
     *
     * REQUEST BODY:
     *
     * {
     *   "cartItem":
     *   {
     *       "sku":"MS10-XS-Black",
     *       "item_id":5853,
     *       "quoteId":"81668"
     *   }
     * }
     *
     */

    public function delete(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');
        $input = $this->getPost();

        $this->load->model('vsbridge/api');

        if($this->validateCartId($cart_id, $token)) {
            if (empty($input['cartItem'])) {
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_missing_cart_item');
                $this->sendResponse();
            }

            if (empty($input['cartItem']['sku']) || !isset($input['cartItem']['item_id'])) {
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_missing_sku_item_id');
                $this->sendResponse();
            }



            $product_info = $this->model_vsbridge_api->getProductBySku($input['cartItem']['sku'], $this->language_id);

            if(empty($product_info)){
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_invalid_sku');
                $this->sendResponse();
            }

            $this->cart->remove($input['cartItem']['item_id']);

            $this->result = true;
        }

        $this->sendResponse();
    }


    /*
     * POST /vsbridge/cart/apply-coupon
     * This method is used to apply the discount code to the current server side quote.
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
     * coupon - coupon code to apply
     */

    public function apply_coupon(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');
        $coupon = $this->getParam('coupon');

        $this->load->model('extension/total/coupon');

        if($this->validateCartId($cart_id, $token)) {
            $coupon_info = $this->model_extension_total_coupon->getCoupon($coupon);

            if(!$coupon_info){
                $this->load->language('extension/total/coupon');
                $this->code = 500;
                $this->result = $this->language->get('error_coupon');
                $this->sendResponse();
            }

            $this->session->data['coupon'] = $coupon;
            $this->result = true;
        }

        $this->sendResponse();
    }

    /*
    * POST /vsbridge/cart/delete-coupon
    * This method is used to delete the discount code to the current server side quote.
    *
    * GET PARAMS:
    * token - null OR user token obtained from /vsbridge/user/login
    * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
    */

    public function delete_coupon(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');

        if($this->validateCartId($cart_id, $token)) {
            unset($this->session->data['coupon']);
            $this->result = true;
        }

        $this->sendResponse();
    }

    /*
    * GET /vsbridge/cart/coupon
    * This method is used to get the currently applied coupon code.
    *
    * GET PARAMS:
    * token - null OR user token obtained from /vsbridge/user/login
    * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
    */

    public function coupon(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');

        if($this->validateCartId($cart_id, $token)) {
            if(!empty($this->session->data['coupon'])){
                $this->result = $this->session->data['coupon'];
            }else{
                $this->result = null;
            }
        }

        $this->sendResponse();
    }


    /*
    * GET /vsbridge/cart/totals
    * Method called when the config.synchronize_totals=true just after any shopping cart modification.
    * It's used to synchronize the Magento / other CMS totals after all promotion rules processed with current Vue Storefront state.
    *
    * GET PARAMS:
    * token - null OR user token obtained from /vsbridge/user/login
    * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
    */

    public function totals(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');

        if($this->validateCartId($cart_id, $token)) {

            $this->load->model('extension/extension');

            $totals = array();
            $taxes = $this->cart->getTaxes();
            $total = 0;

            // Because __call can not keep var references so we put them into an array.
            $total_data = array(
                'totals' => &$totals,
                'taxes'  => &$taxes,
                'total'  => &$total
            );

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

            $cart_totals = array();

            foreach ($totals as $key => $value) {
                switch($value['code']){
                    case 'sub_total':
                        $cart_totals[] = array(
                            'code' => 'subtotal',
                            'title' => $value['title'],
                            'value' => (float)$value['value']
                        );
                        break;
                    case 'shipping':
                        $cart_totals[] = array(
                            'code' => 'shipping',
                            'title' => $value['title'],
                            'value' => (float)$value['value']
                        );
                        break;
                    case 'tax':
                        $cart_totals[] = array(
                            'code' => 'tax',
                            'title' => $value['title'],
                            'value' => (float)$value['value']
                        );
                        break;
                    case 'total':
                        $cart_totals[] = array(
                            'code' => 'grand_total',
                            'title' => $value['title'],
                            'value' => (float)$value['value']
                        );
                        break;
                    case 'coupon':
                        $cart_totals[] = array(
                            'code' => 'discount',
                            'title' => $value['title'],
                            'value' => (float)$value['value']
                        );
                        break;
                }
            }

            $cart_products = $this->cart->getProducts();

            $cart_items = array();

            /* Currently we don't calculate per-product tax/discount. Instead OpenCart calculates the cart total tax */
            foreach($cart_products as $cart_product){
                $cart_items[] = array(
                    'item_id' => (int)$cart_product['cart_id'],
                    'price' => (float)$cart_product['price'],
                    'base_price' => (float)$cart_product['price'],
                    'qty' => (int)$cart_product['quantity'],
                    'row_total' => (float)$cart_product['total'],
                    'base_row_total' => (float)$cart_product['total'],
                    'row_total_with_discount' => (float)$cart_product['total'],
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'base_discount_amount' => 0,
                    'discount_percent' => 0,
                    'name' => $cart_product['name'],
                );
            }

            $response = array(
                'items' => $cart_items,
                'total_segments' => $cart_totals
            );

            $this->result = $response;

        }

        $this->sendResponse();
    }

    /*
     * GET /vsbridge/cart/payment-methods
     * This method is used as a step in the cart synchronization process to get all the payment methods with actual costs as available inside the backend CMS
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
     *
     * Note: this method must be called after a shipment method is set. Otherwise it results in an error.
     */

    public function payment_methods(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');

        if($this->validateCartId($cart_id, $token)) {
            /* We're estimating the payment methods since OpenCart requires address details that aren't provided by VSF at registration */
            $this->load->language('checkout/checkout');

            $country_id = $this->config->get('config_country_id');
            $zone_id = $this->config->get('config_zone_id');

            $this->load->model('extension/extension');

            $this->load->model('localisation/country');
            $country_data = $this->model_localisation_country->getCountry($country_id);

            $this->load->model('localisation/zone');
            $zone_data = $this->model_localisation_zone->getZone($zone_id);

            $this->session->data['payment_address'] = array(
                'firstname'         => '',
                'lastname'          => '',
                'company'           => '',
                'address_1'         => '',
                'address_2'         => '',
                'postcode'          => '',
                'city'              => '',
                'zone_id'           => $zone_id,
                'zone'              => (isset($zone_data['name'])) ? $zone_data['name'] : '',
                'zone_code'         => (isset($zone_data['code'])) ? $zone_data['code'] : '',
                'country_id'        => $country_id,
                'country'           => (isset($country_data['name'])) ? $country_data['name'] : '',
                'iso_code_2'        => (isset($country_data['iso_code_2'])) ? $country_data['iso_code_2'] : '',
                'iso_code_3'        => (isset($country_data['iso_code_3'])) ? $country_data['iso_code_3'] : '',
                'address_format'    => (isset($country_data['address_format'])) ? $country_data['address_format'] : '',
            );

            if (isset($this->session->data['payment_address'])) {
                // Totals
                $totals = array();
                $taxes = $this->cart->getTaxes();
                $total = 0;

                // Because __call can not keep var references so we put them into an array.
                $total_data = array(
                    'totals' => &$totals,
                    'taxes'  => &$taxes,
                    'total'  => &$total
                );

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

                // Payment Methods
                $method_data = array();

                $results = $this->model_extension_extension->getExtensions('payment');

                $recurring = $this->cart->hasRecurringProducts();

                foreach ($results as $result) {
                    if ($this->config->get($result['code'] . '_status')) {
                        $this->load->model('extension/payment/' . $result['code']);

                        $method = $this->{'model_extension_payment_' . $result['code']}->getMethod($this->session->data['payment_address'], $total);

                        if ($method) {
                            if ($recurring) {
                                if (property_exists($this->{'model_extension_payment_' . $result['code']}, 'recurringPayments') && $this->{'model_extension_payment_' . $result['code']}->recurringPayments()) {
                                    $method_data[$result['code']] = $method;
                                }
                            } else {
                                $method_data[$result['code']] = $method;
                            }
                        }
                    }
                }

                $sort_order = array();

                foreach ($method_data as $key => $value) {
                    $sort_order[$key] = $value['sort_order'];
                }

                array_multisort($sort_order, SORT_ASC, $method_data);

                $this->session->data['payment_methods'] = $method_data;
            }

            if (empty($this->session->data['payment_methods'])) {
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_no_payment');
                $this->sendResponse();
            }

            $adjusted_payment_methods = array();

            foreach($this->session->data['payment_methods'] as $payment_method){
                $adjusted_payment_methods[] = array(
                    'code' => $payment_method['code'],
                    'title' => $payment_method['title']
                );
            }

            $this->result = $adjusted_payment_methods;
        }

        $this->sendResponse();
    }


    /*
     * POST /vsbridge/cart/shipping-methods
     * This method is used as a step in the cart synchronization process to get all the shipping methods with actual costs as available inside the backend CMS.
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
     *
     * REQUEST BODY:
     * If the shipping methods are dependent on the full address - probably we need to pass the whole address record with the same format as it's passed to api/order/create or api/user/me.
     * The minimum required field is the country_id.
     *
     * {
     *   "address":
     *     {
     *         "country_id":"PL"
     *     }
     * }
     *
     * Note: since the minimum passed input is country_id, but OpenCart requires more fields, we will use default values unless provided.
     * Update: We decided to ignore country_id (even though it's sent by VSF) and estimate the shipping methods via default config values (since each store is tied to one country).
     */

    public function shipping_methods(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');

        if($this->validateCartId($cart_id, $token)) {
            $this->load->language('api/shipping');

            unset($this->session->data['shipping_methods']);
            unset($this->session->data['shipping_method']);

            /* We're estimating the shipping methods since OpenCart requires address details that aren't provided by VSF at registration */

            $shipping_methods = array();

            $country_id = $this->config->get('config_country_id');
            $zone_id = $this->config->get('config_zone_id');

            $this->load->model('extension/extension');

            $results = $this->model_extension_extension->getExtensions('shipping');

            $this->load->model('localisation/country');
            $country_data = $this->model_localisation_country->getCountry($country_id);

            $this->load->model('localisation/zone');
            $zone_data = $this->model_localisation_zone->getZone($zone_id);

            $this->session->data['shipping_address'] = array(
                'firstname'         => '',
                'lastname'          => '',
                'company'           => '',
                'address_1'         => '',
                'address_2'         => '',
                'postcode'          => '',
                'city'              => '',
                'zone_id'           => $zone_id,
                'zone'              => (isset($zone_data['name'])) ? $zone_data['name'] : '',
                'zone_code'         => (isset($zone_data['code'])) ? $zone_data['code'] : '',
                'country_id'        => $country_id,
                'country'           => (isset($country_data['name'])) ? $country_data['name'] : '',
                'iso_code_2'        => (isset($country_data['iso_code_2'])) ? $country_data['iso_code_2'] : '',
                'iso_code_3'        => (isset($country_data['iso_code_3'])) ? $country_data['iso_code_3'] : '',
                'address_format'    => (isset($country_data['address_format'])) ? $country_data['address_format'] : '',
            );

            foreach ($results as $result) {
                if ($this->config->get($result['code'] . '_status')) {
                    $this->load->model('extension/shipping/' . $result['code']);

                    $quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

                    if ($quote) {
// Clear Thinking: Ultimate Restrictions
                        if (($this->config->get('ultimate_restrictions_status') || $this->config->get('module_ultimate_restrictions_status')) && isset($this->session->data['ultimate_restrictions'])) {
                            foreach ($quote['quote'] as $index => $restricting_quote) {
                                foreach ($this->session->data['ultimate_restrictions'] as $extension => $rules) {
                                    if ($extension != $result['code']) continue;
                                    foreach ($rules as $comparison => $values) {
                                        $adjusted_title = explode('(', $restricting_quote['title']);
                                        $adjusted_title = strtolower(html_entity_decode(trim($adjusted_title[0]), ENT_QUOTES, 'UTF-8'));
                                        if (($comparison == 'is' && in_array($adjusted_title, $values)) || ($comparison == 'not' && !in_array($adjusted_title, $values))) {
                                            unset($quote['quote'][$index]);
                                        }
                                    }
                                }
                            }
                            if (empty($quote['quote'])) {
                                continue;
                            }
                        }
                        // end
                        $shipping_methods[$result['code']] = array(
                            'title'      => $quote['title'],
                            'quote'      => $quote['quote'],
                            'sort_order' => $quote['sort_order'],
                            'error'      => $quote['error']
                        );
                    }
                }
            }

            $sort_order = array();

            foreach ($shipping_methods as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $shipping_methods);

            if (!$shipping_methods) {
                $this->code = 500;
                $this->result = $this->language->get('error_no_shipping');
                $this->sendResponse();
            }

            $this->session->data['shipping_methods'] = $shipping_methods;

            $adjusted_shipping_methods = array();

            foreach($shipping_methods as $smkey => $smvalue){
                $adjusted_shipping_methods[] = array(
                    'carrier_code' => $smvalue['quote'][$smkey]['code'],
                    'method_code' => $smvalue['quote'][$smkey]['code'],
                    'carrier_title' => $smvalue['quote'][$smkey]['title'],
                    'method_title' => $smvalue['quote'][$smkey]['title'],
                    'amount' => (float)$smvalue['quote'][$smkey]['cost'], // Using the shipping price excluding tax since the tax will be applied to the entire order
                    'base_amount' => (float)$smvalue['quote'][$smkey]['cost'],
                    'available' => $smvalue['error'] ? false : true,
                    'error_message' => '',
                    'price_excl_tax' => (float)$smvalue['quote'][$smkey]['cost'],
                    'price_incl_tax' => ($smvalue['quote'][$smkey]['cost']) ? (float)$this->currency->format($this->tax->calculate($smvalue['quote'][$smkey]['cost'], (int)$smvalue['quote'][$smkey]['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], '', false) : 0
                );
            }

            $this->result = $adjusted_shipping_methods;
        }

        $this->sendResponse();
    }

    /*
     * POST /vsbridge/cart/shipping-information
     * This method sets the shipping information on specified quote which is a required step before calling api/cart/collect-totals
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
     *
     * REQUEST BODY:
     *
     *  {
     *      "addressInformation":
     *      {
     *          "shipping_address":
     *          {
     *              "country_id":"PL"
     *          },
     *          "shippingMethodCode":"flatrate",
     *          "shippingCarrierCode":"flatrate"
     *      }
     *  }
     *
     * TODO: Check the resposne body if there are specific fields VSF needs.
     * Note: Changing the array information (i.e. shipping_method_code => shippingMethodCode) due to incorrect API specifications
     */

    public function shipping_information(){
        $token = $this->getParam('token', true);
        $cart_id = $this->getParam('cartId');
        $input = $this->getPost();

        if($this->validateCartId($cart_id, $token)) {
            // Delete old shipping method so not to cause any issues if there is an error
            unset($this->session->data['shipping_method']);

            $this->load->language('api/shipping');

            if ($this->cart->hasShipping()) {
                // Shipping Address
                if (!isset($this->session->data['shipping_address'])) {
                    $this->code = 500;
                    $this->result = $this->language->get('error_address');
                }

                // Shipping Method
                if (empty($this->session->data['shipping_methods'])) {
                    $this->code = 500;
                    $this->result = $this->language->get('error_no_shipping');
                } elseif (!isset($input['addressInformation']['shippingMethodCode'])) {
                    $this->code = 500;
                    $this->result = $this->language->get('error_method');
                } else {
                    $shipping = explode('.', $input['addressInformation']['shippingMethodCode']);

                    if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
                        $this->code = 500;
                        $this->result = $this->language->get('error_method');
                    }
                }

                if (!$this->result) {
                    $this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];

                    $cart_products = $this->cart->getProducts();

                    $cart_items = array();

                    /* Currently we don't calculate per-product tax/discount. Instead OpenCart calculates the cart total tax */
                    foreach($cart_products as $cart_product){
                        $cart_items[] = array(
                            'item_id' => (int)$cart_product['cart_id'],
                            'price' => (float)$cart_product['price'],
                            'base_price' => (float)$cart_product['price'],
                            'qty' => (int)$cart_product['quantity'],
                            'row_total' => (float)$cart_product['total'],
                            'base_row_total' => (float)$cart_product['total'],
                            'row_total_with_discount' => (float)$cart_product['total'],
                            'tax_amount' => 0,
                            'discount_amount' => 0,
                            'base_discount_amount' => 0,
                            'discount_percent' => 0,
                            'name' => $cart_product['name'],
                        );
                    }

                    $this->result = array(
                        'items' => $cart_items,
                        'message' => $this->language->get('text_method')
                    );
                }
            } else {
                unset($this->session->data['shipping_address']);
                unset($this->session->data['shipping_method']);
                unset($this->session->data['shipping_methods']);
            }
        }

        $this->sendResponse();
    }

    /*
     * POST /vsbridge/cart/collect-totals
     * This method is called to update the quote totals just after the address information has been changed.
     *
     * GET PARAMS:
     * token - null OR user token obtained from /vsbridge/user/login
     * cartId - numeric (integer) value for authorized user cart id or GUID (mixed string) for guest cart ID obtained from api/cart/create
     *
     * REQUEST BODY:
     *  {
     *    "methods": {
     *      "paymentMethod": {
     *        "method": "cashondelivery"
     *       },
     *      "shippingCarrierCode": "flatrate",
     *      "shippingMethodCode": "flatrate"
     *    }
     *  }
     *
     * Notes: We haven't found any usage of this method in VSF yet. Will update if necessary since totals are automatically updated via the totals endpoint.
     * TODO: Check the resposne body if there are specific fields VSF needs.
     */

    public function collect_totals(){
        $this->result = array();

        $this->sendResponse();
    }
}