<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeUser extends VsbridgeController{

    private $error = array();

    public function __construct($registry){
        parent::__construct($registry);

        /* Rerouting function calls with illegal PHP characters such as -, etc. */
        $function_name = str_replace('vsbridge/user/', '', $_REQUEST['route']);

        switch($function_name){
            case 'order-history':
                $this->orderHistory();
                die();
                break;
        }
    }

    /*
     * POST /vsbridge/user/create
     * Registers new user to eCommerce backend users database.
     *
     * REQUEST BODY:
     *
     * {
     *    "customer": {
     *      "email": "pkarwatka9998@divante.pl",
     *      "firstname": "Joe",
     *      "lastname": "Black"
     *    },
     *    "password": "SecretPassword"
     *  }
     *
     * Error status code: 500
     */

    public function create(){
        if($fields = $this->register_validate()){
            $this->load->model('account/customer');
            $this->load->model('vsbridge/api');


            /* Load the guest session to retain the cart content */
            $session_id = $this->getSessionId();
            $this->loadSession($session_id);

            $data = array(
                'customer_group_id' => $this->config->get('config_customer_group_id'),
                'firstname' => $fields['customer']['firstname'],
                'lastname' => $fields['customer']['lastname'],
                'email' => $fields['customer']['email'],
                'telephone' => '',
                'fax' => '',
                'newsletter' => 0,
                'password' =>  $fields['password'],
                'status' => 1,
                'approved' => 1,
                'country_id' => $this->config->get('config_country_id'),
                'company' => '',
                'address_1' => '',
                'address_2' => '',
                'city' => '',
                'postcode' => '',
                'zone_id' => $this->config->get('config_zone_id'),
                'safe' => 0
            );

            if($customer_id = $this->model_account_customer->addCustomer($data)){

                // Delete the default address row and address_id (this is done to prompt the user for their detail on the checkout page)
                $this->model_vsbridge_api->deleteDefaultCustomerAddress($customer_id);

                // Clear any previous login attempts for unregistered accounts.
                $this->model_account_customer->deleteLoginAttempts($data['email']);

                unset($this->session->data['guest']);

                // Add to activity log
                if ($this->config->get('config_customer_activity')) {
                    $this->load->model('account/activity');

                    $activity_data = array(
                        'customer_id' => $customer_id,
                        'name'        => $data['firstname'] . ' ' . $data['lastname']
                    );

                    $this->model_account_activity->addActivity('register', $activity_data);
                }


                $this->result = array(
                    'id' => $customer_id,
                    'group_id' => $data['customer_group_id'],
                    'firstname' => $data['firstname'],
                    'lastname' => $data['lastname'],
                    'email' => $data['email']
                );
            }else{
                $this->code = 500;

                $this->load->language('vsbridge/api');
                $this->result = $this->language->get('error_create_new_customer');
            }
        }

        $this->sendResponse();
    }

    public function register_validate(){
        $input = $this->getPost();

        $this->load->model('account/customer');
        $this->load->language('account/register');

        if(!empty($input['customer']['firstname'])){
            if ((utf8_strlen(trim($input['customer']['firstname'])) < 1) || (utf8_strlen(trim($input['customer']['firstname'])) > 32)) {
                $this->error[] = $this->language->get('error_firstname');
            }
        }else{
            $this->error[] = $this->language->get('error_firstname');
        }


        if(!empty($input['customer']['lastname'])){
            if ((utf8_strlen(trim($input['customer']['lastname'])) < 1) || (utf8_strlen(trim($input['customer']['lastname'])) > 32)) {
                $this->error[] = $this->language->get('error_lastname');
            }
        }else{
            $this->error[] = $this->language->get('error_lastname');
        }

        if(!empty($input['customer']['email'])) {
            if ((utf8_strlen($input['customer']['email']) > 96) || !preg_match('/^[^\@]+@.*.[a-z]{2,15}$/i', $input['customer']['email'])) {
                $this->error[] = $this->language->get('error_email');
            }else{
                $customer_info = $this->model_account_customer->getCustomerByEmail($input['customer']['email']);

                if ($customer_info) {
                    $this->error[] = $this->language->get('error_exists');
                }
            }
        }else{
            $this->error[] = $this->language->get('error_email');
        }

        if(!empty($input['password'])){
            if ((utf8_strlen($input['password']) < 4) || (utf8_strlen($input['password']) > 20)) {
                $this->error[] = $this->language->get('error_password');
            }
        }else{
            $this->error[] = $this->language->get('error_password');
        }

        if(!empty($this->error)){
            $this->code = 500;
            $this->result = implode(' ', $this->error);
            return false;
        }else{
            return $input;
        }
    }

    /*
     * POST /vsbridge/user/login
     * Authorizes the user. It's called after user submits "Login" form inside the Vue Storefront app.
     * It returns the user token which should be used for all subsequent API calls that requires authorization
     *
     * REQUEST BODY:
     *
     * {
     *   "username": "pkarwatka102@divante.pl",
     *   "password": "TopSecretPassword"
     * }
     *
     * The result is a authorization token, that should be passed via ?token=xu8h02nd66yq0gaayj4x3kpqwity02or GET param to all subsequent API calls that requires authorization.
     *
     * Logic and security:
     *   Every time the customer logs in, an access token and a long-lived (30-days) refresh token will be given back.
     *   Vue-storefront will regularly ask for a new access token via the refresh token without the need for customer credentials.
     *   The current implementation allows for monitoring and controlling all login instances.
     *   TODO: When the customer resets their password, an automatic function call should delete all the access tokens and refresh tokens linked to the customer_id, forcing them to re-login.
     *
     * Error status code: 500
     */

    public function login(){
        $custom_fields = null;

        if($customer_info = $this->login_validate()){
            $this->load->model('vsbridge/api');

            $token = $this->model_vsbridge_api->addCustomerToken($customer_info['customer_id'], $this->request->server['REMOTE_ADDR']);

            $refresh_token_id = $this->model_vsbridge_api->addCustomerRefreshToken($customer_info['customer_id'], $this->request->server['REMOTE_ADDR']);

            $refresh_token = $this->getToken($refresh_token_id, true);

            if($token && $refresh_token){
                $this->load->model('account/customer');

                /* Load an existing customer / guest session if possible. Otherwise, create a new session. */
                if($session_id = $this->getSessionId($customer_info['customer_id'])){

                    /* Switch to the customer session */
                    $this->loadSession($session_id);

                    $this->customer->login($customer_info['email'], '', true);

                    $this->result = $token;

                    $custom_fields = array(
                        'meta' => array(
                            'refreshToken' => $refresh_token
                        )
                    );

                }else{
                    $this->code = 500;
                    $this->load->language('vsbridge/api');
                    $this->result = $this->language->get('error_customer_session_id');
                }

            }else{
                $this->code = 500;
                $this->load->language('vsbridge/api');
                $this->result = $this->language->get('error_generate_login_tokens');
            }
        }

        $this->sendResponse(false, $custom_fields ?: null);
    }

    public function login_validate(){
        $input = $this->getPost();
        $customer_info = null;

        $this->load->model('account/customer');
        $this->load->language('account/login');
        $this->load->language('vsbridge/api');

        if(empty($input['username'])){

            $this->error[] = $this->language->get('error_missing_username');

        }elseif(empty($input['password'])){

            $this->error[] = $this->language->get('error_missing_password');

        }else{
            $login_info = $this->model_account_customer->getLoginAttempts($input['username']);

            if ($login_info && ($login_info['total'] >= $this->config->get('config_login_attempts')) && strtotime('-1 hour') < strtotime($login_info['date_modified'])) {
                $this->error[] = $this->language->get('error_attempts');
            }

            // Check if customer has been approved.
            $customer_info = $this->model_account_customer->getCustomerByEmail($input['username']);

            if ($customer_info && !$customer_info['approved']) {
                $this->error[] = $this->language->get('error_approved');
            }else{
                if (!$this->customer->login($input['username'], $input['password'])) {
                    $this->error[] = $this->language->get('error_login');

                    $this->model_account_customer->addLoginAttempt($input['username']);
                } else {
                    $this->model_account_customer->deleteLoginAttempts($input['username']);
                }
            }

        }

        if(!empty($this->error)){
            $this->code = 500;
            $this->result = implode(' ', $this->error);
            return false;
        }else{
            return $customer_info;
        }
    }

    /*
     * POST /vsbridge/user/refresh
     * Refresh the user token
     *
     * REQUEST BODY:
     *
     * {
     *   "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6IjEzOSJ9.a4HQc2HODmOj5SRMiv-EzWuMZbyIz0CLuVRhPw_MrOM"
     * }
     *
     * The result is a authorization token, that should be passed via ?token=xu8h02nd66yq0gaayj4x3kpqwity02or GET param to all subsequent API calls that requires authorization.
     *
     * Error status code: 500
     */

    public function refresh(){
        $input = $this->getPost();
        $this->load->language('vsbridge/api');

        if(!empty($input['refreshToken'])){

            if($refresh_token_id = $this->validateToken($input['refreshToken'], true)){

                $this->load->model('vsbridge/api');

                if($refresh_token_info = $this->model_vsbridge_api->getCustomerRefreshToken($refresh_token_id)){

                    if(isset($refresh_token_info['customer_id'])){

                        if($token = $this->model_vsbridge_api->addCustomerToken($refresh_token_info['customer_id'], $this->request->server['REMOTE_ADDR'])){
                            $this->result = $token;
                        }else{
                            $this->error[] = $this->language->get('error_generate_new_token');
                        }

                    }else{
                        $this->error[] = $this->language->get('error_retrieve_customer_info');
                    }

                }else{
                    $this->error[] = $this->language->get('error_invalid_refresh_token');
                }

            }else{
                $this->error[] = $this->language->get('error_invalid_refresh_token');
            }

        }else{
            $this->error[] = $this->language->get('error_missing_refresh_token');
        }


        if(!empty($this->error)){
            $this->code = 500;
            $this->result = implode(' ', $this->error);
        }

        $this->sendResponse();
    }

    /*
     * POST /vsbridge/user/resetPassword
     * Sends the password reset link for the specified user.
     *
     * REQUEST BODY:
     *
     * {
     *    "email": "pkarwatka992@divante.pl"
     * }
     *
     * Error status code: 500
     *
     * TODO: Vue-storefront currently doesn't have a UI/API to serve the reset link. We currently use the old OpenCart interface.
     *       Issue: https://github.com/DivanteLtd/vue-storefront/issues/2576
     */

    public function resetPassword(){
        $this->load->model('account/customer');
        $this->load->language('mail/forgotten');
        $this->load->language('account/forgotten');

        if($input = $this->resetPassword_validate()){
            $code = token(40);

            $this->model_account_customer->editCode($input['email'], $code);

            $subject = sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));

            $message  = sprintf($this->language->get('text_greeting'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8')) . "\n\n";
            $message .= $this->language->get('text_change') . "\n\n";
            $message .= $this->url->link('account/reset', 'code=' . $code, true) . "\n\n";
            $message .= sprintf($this->language->get('text_ip'), $this->request->server['REMOTE_ADDR']) . "\n\n";

            // Prepare mail: customer.forgotten
            $this->load->model('extension/mail/template');

            $this->load->model('account/customer');

            $email = $input['email'];

            $customer_info = $this->model_account_customer->getCustomerByEmail($email);

            $template_load = array('key' => 'customer.forgotten');

            if ($customer_info) {
                $template_load['customer_id'] = $customer_info['customer_id'];
                $template_load['customer_group_id'] = $customer_info['customer_group_id'];
                $template_load['language_id'] = $customer_info['language_id'];
                $template_load['store_id'] = $customer_info['store_id'];
            }

            $template = $this->model_extension_mail_template->load($template_load);

            if ($template) {
                if (isset($input['email'])) {
                    $template->addData($input['email']);
                }

                if ($customer_info) {
                    $template->addData($customer_info, 'customer');
                }

                if (!empty($template->data['text_greeting'])) {
                    $template->data['text_greeting'] = sprintf($template->data['text_greeting'], $template->data['store_name']);
                }

                $template->data['password_link'] = $this->url->link('account/reset', 'email=' . urlencode($email) . '&code=' . $code);

                if (!empty($template->data['button_password_link'])) {
                    $template->data['password_link_text'] = $template->data['button_password_link'];
                } else {
                    $template->data['password_link_text'] = $template->data['password_link'];
                }

                $template->data['account_login'] = $this->url->link('account/login');

                if (!empty($template->data['button_account_login'])) {
                    $template->data['account_login_text'] = $template->data['button_account_login'];
                } else {
                    $template->data['account_login_text'] = $template->data['account_login'];
                }
                // Prepared mail: customer.forgotten
            }

            $mail = new Mail();
            $mail->protocol = $this->config->get('config_mail_protocol');
            $mail->parameter = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

            $mail->setTo($input['email']);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
            $mail->setText(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));

            // Send mail: customer.forgotten
            if ($template) {
                $template->build();
                $template->hook($mail);

                $mail->send();

                $this->model_extension_mail_template->sent();
            }

            $this->result = $this->language->get('text_success');

            // Add to activity log
            if ($this->config->get('config_customer_activity')) {
                $customer_info = $this->model_account_customer->getCustomerByEmail($input['email']);

                if ($customer_info) {
                    $this->load->model('account/activity');

                    $activity_data = array(
                        'customer_id' => $customer_info['customer_id'],
                        'name'        => $customer_info['firstname'] . ' ' . $customer_info['lastname']
                    );

                    $this->model_account_activity->addActivity('forgotten', $activity_data);
                }
            }
        }

        $this->sendResponse();
    }

    public function resetPassword_validate(){
        $input = $this->getPost();
        $this->load->language('account/forgotten');

        if(!empty($input['email'])) {
            if ((utf8_strlen($input['email']) > 96) || !preg_match('/^[^\@]+@.*.[a-z]{2,15}$/i', $input['email'])) {
                $this->error[] = $this->language->get('error_email');
            }elseif(!$this->model_account_customer->getTotalCustomersByEmail($input['email'])){
                $this->error[] = $this->language->get('error_email');
            }
        }else{
            $this->error[] = $this->language->get('error_email');
        }

        if(!empty($this->error)){
            $this->code = 500;
            $this->result = implode(' ', $this->error);
            return false;
        }else{
            return $input;
        }
    }

    /*
     * POST /vsbridge/user/changePassword
     * This method is used to change password for current user identified by token obtained from api/user/login
     *
     * GET PARAMS:
     * token - user token returned from POST /vsbridge/user/login
     *
     * REQUEST BODY:
     *
     * {
     *   "currentPassword":"OldPassword",
     *   "newPassword":"NewPassword"
     * }
     *
     * Note: There is no requirement for the current password in OpenCart in order to reset the customer's password.
     *       Also, this would not work with auto-generated passwords and magic links.
     *
     * Error status code: 500
     */

    public function changePassword(){
        if($input = $this->changePassword_validate()) {

            $this->load->model('account/customer');

            $this->model_account_customer->editPassword($input['customer_info']['email'], $input['newPassword']);

            if ($this->config->get('config_customer_activity')) {
                $this->load->model('account/activity');

                $activity_data = array(
                    'customer_id' => $input['customer_info']['customer_id'],
                    'name' => $input['customer_info']['firstname'] . ' ' . $input['customer_info']['lastname']
                );

                $this->model_account_activity->addActivity('reset', $activity_data);
            }

            $this->load->language('account/reset');

            $this->result = $this->language->get('text_success');

        }

        $this->sendResponse();
    }

    public function changePassword_validate(){
        $input = $this->getPost();
        $this->load->language('account/reset');
        $this->load->language('vsbridge/api');

        $token = $this->getParam('token');

        if($input['customer_info'] = $this->validateCustomerToken($token)){

            /*if(!empty($input['currentPassword'])) {*/

            if(!empty($input['newPassword'])) {

                if ((utf8_strlen($input['newPassword']) < 4) || (utf8_strlen($input['newPassword']) > 20)) {
                    $this->error[] = $this->language->get('error_password');
                }

            }else{
                $this->error[] = $this->language->get('error_missing_newpassword');
            }

            /*}else{
                $this->error[] = 'Missing currentPassword.';
            }*/

        }

        if(!empty($this->error)){
            $this->code = 500;
            $this->result = implode(' ', $this->error);
            return false;
        }else{
            return $input;
        }
    }

    /*
     * GET /vsbridge/user/order-history
     * Get the user order history from server side
     *
     * GET PARAMS:
     * token - user token returned from POST /vsbridge/user/login
     */

    public function orderHistory(){
        $token = $this->getParam('token');

        if($input['customer_info'] = $this->validateCustomerToken($token)) {

            $this->load->model('vsbridge/api');

            $orders = $this->model_vsbridge_api->getCustomerOrders($input['customer_info']['customer_id']);

            $adjusted_orders = array();

            foreach($orders as $order){

                $adjusted_order_products = array();

                $order_products = $this->model_vsbridge_api->getOrderProducts($order['order_id']);

                foreach($order_products as $order_product){

                    $product_info = $this->model_vsbridge_api->getProduct($order_product['product_id']);

                    $current_product = array(
                        'item_id' => (int) $order_product['product_id'],
                        'name' => $order_product['name'],
                        'sku' => $product_info['sku'],
                        'model' => $product_info['model'],
                        'price_incl_tax' => floatval($order_product['price']) + floatval($order_product['tax']),
                        'qty_ordered' => (int) $order_product['quantity'],
                        'row_total_incl_tax' => floatval($order_product['total']) + floatval($order_product['tax']),

                    );

                    array_push($adjusted_order_products, $current_product);
                }

                /* TODO: What is discount_tax_compensation_amount? Do we need it? */

                $current_order = array(
                    'order_id' => (int) $order['order_id'],
                    'entity_id' => (int) $order['order_id'],
                    'status' => $this->model_vsbridge_api->getOrderStatus($order['order_status_id'], $this->language_id)['name'],
                    'created_at' => $order['date_added'],
                    'items' => $adjusted_order_products,
                    'discount_tax_compensation_amount' => 0
                );

                $order_totals = $this->model_vsbridge_api->getOrderTotals($order['order_id']);

                foreach($order_totals as $order_total){

                    switch($order_total['code']){

                        case 'sub_total':
                            $current_order['subtotal'] = (float) $order_total['value'];
                            break;

                        case 'shipping':
                            $current_order['shipping_amount'] = (float) $order_total['value'];
                            break;

                        case 'tax':
                            $current_order['tax_amount'] = (float) $order_total['value'];
                            break;

                        case 'coupon':
                            $current_order['discount_amount'] = (float) $order_total['value'];
                            break;

                        case 'total':
                            $current_order['grand_total'] = (float) $order_total['value'];
                            break;
                    }

                }

                array_push($adjusted_orders, $current_order);
            }

            $response = array(
                'items' => $adjusted_orders,
                'total_count' => count($adjusted_orders)
            );

            $this->result = $response;
        }

        $this->sendResponse();
    }

    /*
     * GET /vsbridge/user/me
     * Gets the User profile for the currently authorized user. It's called after POST /vsbridge/user/login successful call.
     *
     * GET PARAMS:
     * token - user token returned from POST /vsbridge/user/login
     *
     * --------------
     *
     * POST /vsbridge/user/me
     * Updates the user address and other data information.
     *
     * GET PARAMS:
     * token - user token returned from POST /vsbridge/user/login
     *
     */

    public function me(){
        $token = $this->getParam('token');

        if($input['customer_info'] = $this->validateCustomerToken($token)) {

            $this->load->model('vsbridge/api');

            /* Check if the method is POST and an update is requested */
            if($update_fields = $this->me_validate($input['customer_info'])){

                foreach($update_fields as $field => $contents){

                    switch($field){

                        case 'email':
                            $this->model_vsbridge_api->editCustomer($input['customer_info']['customer_id'], array(
                                'field' => 'email',
                                'value' => $contents,
                                'type' => 'string'
                            ));
                            break;

                        case 'firstname':
                            $this->model_vsbridge_api->editCustomer($input['customer_info']['customer_id'], array(
                                'field' => 'firstname',
                                'value' => $contents,
                                'type' => 'string'
                            ));
                            break;

                        case 'lastname':
                            $this->model_vsbridge_api->editCustomer($input['customer_info']['customer_id'], array(
                                'field' => 'lastname',
                                'value' => $contents,
                                'type' => 'string'
                            ));
                            break;

                        case 'addresses':
                            if(!empty($contents)){
                                /* Delete all the current customer addresses */
                                $this->model_vsbridge_api->deleteCustomerAddresses($input['customer_info']['customer_id']);

                                /* Add the new addresses */
                                foreach($contents as $address){

                                    $address_data = array();

                                    $default_address = false;

                                    foreach($address as $address_key => $address_value){

                                        /* Telephone is not an address field in OC, but rather a customer field */
                                        if($address_key == 'telephone') {

                                            $this->model_vsbridge_api->editCustomer($input['customer_info']['customer_id'], array(
                                                'field' => 'telephone',
                                                'value' => $address_value,
                                                'type' => 'string'
                                            ));

                                        /* This determines if the inserted address will become the default address */
                                        }elseif($address_key == 'default_shipping'){
                                            if($address_value == true){
                                                $default_address = true;
                                            }
                                        }else{

                                            switch($address_key){

                                                case 'firstname':
                                                    array_push($address_data, array(
                                                        'field' => 'firstname',
                                                        'value' => $address_value,
                                                        'type' => 'string'
                                                    ));
                                                    break;

                                                case 'lastname':
                                                    array_push($address_data, array(
                                                        'field' => 'lastname',
                                                        'value' => $address_value,
                                                        'type' => 'string'
                                                    ));
                                                    break;

                                                case 'city':
                                                    array_push($address_data, array(
                                                        'field' => 'city',
                                                        'value' => $address_value,
                                                        'type' => 'string'
                                                    ));
                                                    break;

                                                case 'postcode':
                                                    array_push($address_data, array(
                                                        'field' => 'postcode',
                                                        'value' => $address_value,
                                                        'type' => 'string'
                                                    ));
                                                    break;

                                                case 'country_id':
                                                    if($country_id = $this->model_vsbridge_api->getCountryIdFromCode($address_value)){
                                                        array_push($address_data, array(
                                                            'field' => 'country_id',
                                                            'value' => $country_id,
                                                            'type' => 'integer'
                                                        ));
                                                    }
                                                    break;

                                                case 'region':
                                                    if(!empty($address_value['region'])){
                                                        if($zone_id = $this->model_vsbridge_api->getZoneIdFromName($address_value['region'])){
                                                            array_push($address_data, array(
                                                                'field' => 'zone_id',
                                                                'value' => $zone_id,
                                                                'type' => 'integer'
                                                            ));
                                                        }
                                                    }
                                                    break;

                                                case 'street':
                                                    if(!empty($address_value)){
                                                        array_push($address_data, array(
                                                            'field' => 'address_1',
                                                            'value' => $address_value,
                                                            'type' => 'string'
                                                        ));
                                                    }
                                                    break;

                                            }

                                        }

                                    }

                                    $this->model_vsbridge_api->addCustomerAddress($input['customer_info']['customer_id'], $address_data, $default_address);

                                }

                            }
                            break;

                    }

                }

                /* Reload the data */
                $input['customer_info'] = $this->validateCustomerToken($token);
            }

            /* Load the response */

            $addresses = $this->model_vsbridge_api->getCustomerAddresses($input['customer_info']['customer_id']);

            $customer_addresses = array();

            foreach($addresses as $address){

                $zone = $this->model_vsbridge_api->getZone($address['zone_id']);

                $region_array = array();

                if(isset($zone['code'])){
                    $region_array['region_code'] = $zone['code'];
                }

                if(isset($zone['name'])){
                    $region_array['region'] = $zone['name'];
                }

                if(isset($zone['zone_id'])){
                    $region_array['region_id'] = $zone['zone_id'];
                }

                if(!empty($address['address_2'])){
                    $street = array(trim($address['address_1']));
                }else{
                    $street = array(trim($address['address_1'].' '.$address['address_2']));
                }

                $current_address = array(
                    'id' => (int) $address['address_id'],
                    'customer_id' => (int) $address['customer_id'],
                    'region_id' => (int) $address['zone_id'],
                    'country_id' => $this->model_vsbridge_api->getCountry($address['country_id'])['iso_code_2'],
                    'street' => $street,
                    'telephone' => $input['customer_info']['telephone'],
                    'postcode' => $address['postcode'],
                    'city' => $address['city'],
                    'firstname' => $address['firstname'],
                    'lastname' => $address['lastname'],
                    'company' => $address['company']
                );

                if(intval($address['address_id']) == intval($input['customer_info']['address_id'])){
                    $current_address['default_shipping'] = true;
                }

                if(!empty($region_array)){
                    $current_address['region'] = $region_array;
                }

                array_push($customer_addresses, $current_address);
            }

            $adjusted_customer_info = array(
                'id' => (int) $input['customer_info']['customer_id'],
                'group_id' => (int) $input['customer_info']['customer_group_id'],
                'default_shipping' => (int) $input['customer_info']['address_id'],
                'email' => $input['customer_info']['email'],
                'firstname' => $input['customer_info']['firstname'],
                'lastname' => $input['customer_info']['lastname'],
                'store_id' => (int) $input['customer_info']['store_id'],
                'addresses' => $customer_addresses
            );


            $this->result = $adjusted_customer_info;
        }

        $this->sendResponse();
    }

    public function me_validate($token_customer_info){
        $input = $this->getPost();

        $this->load->language('account/register');
        $this->load->language('account/address');
        $this->load->language('account/edit');
        $this->load->language('vsbridge/api');

        $update_fields = array();

        if(isset($input['customer'])){

            if(!empty($input['customer']['email'])){
                if ((utf8_strlen($input['customer']['email']) > 96) || !preg_match('/^[^\@]+@.*.[a-z]{2,15}$/i', $input['customer']['email'])) {
                    $this->error[] = $this->language->get('error_email');
                }else{
                    $customer_info = $this->model_account_customer->getCustomerByEmail($input['customer']['email']);

                    if ($customer_info && utf8_strtolower(trim($input['customer']['email'])) != utf8_strtolower(trim($token_customer_info['email']))) {
                        $this->error[] = $this->language->get('error_exists');
                    }else{
                        $update_fields['email'] = $input['customer']['email'];
                    }
                }
            }else{
                $this->error[] = $this->language->get('error_email');
            }

            if(!empty($input['customer']['firstname'])){
                if ((utf8_strlen(trim($input['customer']['firstname'])) < 1) || (utf8_strlen(trim($input['customer']['firstname'])) > 32)) {
                    $this->error[] = $this->language->get('error_firstname');
                }else{
                    $update_fields['firstname'] = $input['customer']['firstname'];
                }
            }else{
                $this->error[] = $this->language->get('error_firstname');
            }

            if(!empty($input['customer']['lastname'])){
                if ((utf8_strlen(trim($input['customer']['lastname'])) < 1) || (utf8_strlen(trim($input['customer']['lastname'])) > 32)) {
                    $this->error[] = $this->language->get('error_lastname');
                }else{
                    $update_fields['lastname'] = $input['customer']['lastname'];
                }
            }else{
                $this->error[] = $this->language->get('error_lastname');
            }

            if(!empty($input['customer']['addresses'])){

                $addresses = array();

                foreach($input['customer']['addresses'] as $address){

                    $current_address = array();

                    if(!empty($address['firstname'])){
                        if ((utf8_strlen(trim($address['firstname'])) < 1) || (utf8_strlen(trim($address['firstname'])) > 32)) {
                            $this->error[] = $this->language->get('error_firstname');
                        }else{
                            $current_address['firstname'] = $address['firstname'];
                        }
                    }else{
                        $this->error[] = $this->language->get('error_firstname');
                    }

                    if(!empty($address['lastname'])){
                        if ((utf8_strlen(trim($address['lastname'])) < 1) || (utf8_strlen(trim($address['lastname'])) > 32)) {
                            $this->error[] = $this->language->get('error_lastname');
                        }else{
                            $current_address['lastname'] = $address['lastname'];
                        }
                    }else{
                        $this->error[] = $this->language->get('error_lastname');
                    }

                    if(!empty($address['city'])){
                        if ((utf8_strlen(trim($address['city'])) < 2) || (utf8_strlen(trim($address['city'])) > 128)) {
                            $this->error['city'] = $this->language->get('error_city');
                        }else{
                            $current_address['city'] = $address['city'];
                        }
                    }else{
                        $this->error['city'] = $this->language->get('error_city');
                    }

                    if(!empty($address['default_shipping'])){
                        $current_address['default_shipping'] = true;
                    }

                    if(!empty($address['postcode'])){
                        if(utf8_strlen(trim($address['postcode'])) < 2 || utf8_strlen(trim($address['postcode'])) > 10){
                            $this->error['postcode'] = $this->language->get('error_postcode');
                        }else{
                            $current_address['postcode'] = $address['postcode'];
                        }
                    }else{
                        $this->error['postcode'] = $this->language->get('error_postcode');
                    }

                    if(!empty($address['country_id'])){
                        $current_address['country_id'] = $address['country_id'];
                    }else{
                        $this->error[] = $this->language->get('error_country');
                    }

                    if(!empty($address['region']['region'])){
                        $current_address['region']['region'] = $address['region']['region'];
                    }

                    if(!empty($address['street'])){
                        $current_address['street'] = implode(' ', $address['street']);
                    }else{
                        $this->error[] = $this->language->get('error_country');
                    }

                    if(!empty($address['telephone'])){
                        if ((utf8_strlen($address['telephone']) < 3) || (utf8_strlen($address['telephone']) > 32)) {
                            $this->error['telephone'] = $this->language->get('error_telephone');
                        }else{
                            $current_address['telephone'] = $address['telephone'];
                        }
                    }else{
                        $this->error['telephone'] = $this->language->get('error_telephone');
                    }

                    array_push($addresses, $current_address);
                }

                $update_fields['addresses'] = $addresses;
            }



        }

        if(!empty($this->error)){
            $this->code = 500;
            $this->result = implode(' ', $this->error);
            $this->sendResponse();
        }else{
            return $update_fields;
        }
    }

}