<?php
/*
 * Vue Storefront Integration for OpenCart (AKA VS Bridge)
 * Made with <3 @ Butopea.com
 */

error_reporting(E_ALL & ~E_NOTICE);

use ReallySimpleJWT\Token;

abstract class VsbridgeController extends Controller {

    public $code = 200;
    public $result;

    public $language_id;
    public $language_code;
    public $store_id;

    public $session_id_prefix = 'vs-';

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->checkExtensionStatus();

        $this->checkEndpointStatus();

        $this->load->model('vsbridge/api');
        
        $this->language_id = $this->getLanguageId();
        $this->store_id = (int) $this->config->get('config_store_id');

        $this->language_code = 'en';
        $config_language = $this->model_vsbridge_api->getStoreConfig('config_language', $this->store_id);

        if(!empty($config_language['value'])){
          $this->language_code = explode('-', $config_language['value'])[0];
        }

        /* HTTP_ACCEPT_LANGUAGE fix */
        if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        }
    }

    /* Prevent access to controllers if the extension is disabled */
    public function checkExtensionStatus()
    {
        if((int)$this->config->get('vsbridge_status') !== 1) {
            $this->load->language('vsbridge/api');
            $this->code = 400;
            $this->result = $this->language->get('error_extension_disabled');
            $this->sendResponse();
        }
    }

    /* Prevent access to API endpoint if disabled in extension settings */
    public function checkEndpointStatus()
    {
        $class_name = strtolower(preg_replace('/\B([A-Z])/', '_$1', get_class($this)));
        $endpoint_name = str_replace('controller_vsbridge_', '', $class_name);

        if(empty($this->config->get('vsbridge_endpoint_statuses')[$endpoint_name])) {
            $this->load->language('vsbridge/api');
            $this->code = 400;
            $this->result = $this->language->get('error_api_endpoint_disabled');
            $this->sendResponse();
        }
    }

    /* Retrieve the language ID from the config */
    public function getLanguageId(){
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $language_code = $this->config->get('config_language');
        $language_id = null;

        foreach($languages as $language){
            if($language['code'] == $language_code){
                $language_id = $language['language_id'];
            }
        }

        if($language_id != null){
            return (int) $language_id;
        }else{
            $this->code = 400;
            $this->result = "Failed to retrieve the langauge ID.";
            $this->sendResponse();
        }
    }

    /* Render the API response */
    public function sendResponse($no_code = null, $custom_fields = null){
        http_response_code((int) $this->code);

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');

        $response_array = array(
            "code" => (int) $this->code,
            "result" => $this->result
        );

        if($no_code){
            $response_array = $this->result;
        }

        if($custom_fields){
            foreach($custom_fields as $custom_field_key => $custom_field_value){
                $response_array[$custom_field_key] = $custom_field_value;
            }
        }

        $this->response->setOutput(json_encode($response_array));

        $this->response->output();

        /* DO NOT REMOVE - This halts the rest of the code from being executed. Necessary when used to terminate the stack due to an error. */
        die();
    }

    /* Get the POST JSON payload */
    public function getPost(){
        $inputJSON = file_get_contents('php://input');

        $post = json_decode($inputJSON, TRUE);

        if ((!is_array($post) || empty($post)) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->code = 400;
            $this->result = "Input JSON is invalid. Request body must be valid JSON if content type is application/json.";
            return $this->sendResponse();
        }

        return $post;
    }

    /* Retrieve a GET parameter */
    public function getParam($parameter, $optional = null){
        if(isset($_GET[$parameter])) {
            return $_GET[$parameter];
        }elseif($optional){
            return false;
        }else{
            $this->code = 400;
            $this->result = "Invalid request. Missing ".$parameter." GET parameter.";
            $this->sendResponse();
        }
    }

    /* Check if the input index exists and matches with a given value */
    /* We use this to avoid multiple if statements for undefined arrays and indexes */
    public function checkIndexValue($input, $index, $value, $wrapper_function = null){
        if(isset($input[$index])){

            /* simple value match */
            if($input[$index] == $value){
                return true;
            }

            /* wrap a function around the index */
            if(is_callable($wrapper_function) && ($wrapper_function($input[$index]) == $value)){
                return true;
            }
        }

        return false;
    }

    /* Check if the input index exists and whether or not it's empty */
    public function checkInput($input, $index, $required = false, $empty_allowed = true, $value_if_empty = null){
        if(isset($input[$index])){
            if(empty($input[$index])){
                if($empty_allowed == false){
                    $this->load->language('vsbridge/api');
                    $this->code = 500;
                    $this->result = $this->language->get('error_empty_input').$index;
                    $this->sendResponse();
                }else{
                    return $value_if_empty;
                }
            }else{
                return $input[$index];
            }
        }else{
            if($required == true){
                $this->load->language('vsbridge/api');
                $this->code = 500;
                $this->result = $this->language->get('error_missing_input').$index;
                $this->sendResponse();
            }else{
                if($value_if_empty){
                    return $value_if_empty;
                }else{
                    return false;
                }
            }
        }
    }

    /* Get the client's IP address */
    public function getClientIp() {
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    /* Generate a JWT token */
    /* A secret key must be configured in the module settings for this to work! */
    protected function getToken($oc_token, $refresh_token = null) {
        $this->load->model('setting/setting');

        if(!empty($this->config->get('vsbridge_secret_key'))){

            try {
                $secret_key = $this->config->get('vsbridge_secret_key');
                // Normal JWT tokens expire every 60 minutes
                // Note: Currently vue-storefront only uses JWT tokens for the importer access tokens and the customer refresh tokens
                //       Normal customer tokens are non-JWT and generated by OpenCart and linked to a session_id
                $expiration = time() + 3600;

                if($refresh_token){
                    // Refresh tokens are set to expire every 30 days, after which the user has to login again
                    $expiration = time() + 2592000;
                }

                $issuer = HTTPS_SERVER;
                $token = Token::create($oc_token, $secret_key, $expiration, $issuer);

                return $token;

            } catch (Exception $e) {
                $this->code = 401;
                if($refresh_token){
                    $this->code = 500;
                }
                $this->result = $e->getMessage();
                $this->sendResponse();
            }

        }else{
            $this->code = 500;
            $this->result = "Module not configured. Please provide a secret key.";
            $this->sendResponse();
        }
    }

    /* Validate the JWT token and, if successful, load the session */
    protected function validateToken($token, $customer_auth = NULL){
        if(!empty($this->config->get('vsbridge_secret_key'))){

            $secret_key = $this->config->get('vsbridge_secret_key');

            if(Token::validate($token, $secret_key)) {
                $payload = Token::getPayload($token, $secret_key);
                $oc_token = $payload['user_id'];

                if($customer_auth){
                    return $oc_token;
                }else{
                    /* Validate the OC API session & verify client IP address */
                    $this->load->model('vsbridge/api');
                    $api_session = $this->model_vsbridge_api->getApiSession($oc_token);

                    if($this->checkIndexValue($api_session, 'ip', $this->getClientIp(), 'trim')){

                        /* sendResponse will automatically switch over to the default session */
                        return $this->session->start('api', $api_session['session_id']);

                    }else{
                        $this->code = 401;
                        if($customer_auth){
                            $this->code = 500;
                        }
                        $this->result = "Authentication failed. Your IP address is not authorized to use this token.";
                        $this->sendResponse();
                    }
                }
            }

        }else{
            $this->code = 500;
            $this->result = "Module not configured. Please provide a secret key.";
            $this->sendResponse();
        }

        $this->code = 401;
        if($customer_auth){
            $this->code = 500;
        }
        $this->result = "Authentication failed. Invalid token.";
        $this->sendResponse();
    }

    /* Retrieve or generate customer/guest session ID and save it in the database (because Vue Storefront does not use cookies/sessions) */
    protected function getSessionId($customer_id = null){
        $new_session_id = md5(uniqid(rand(), true));
        $new_session_id = substr_replace($new_session_id, $this->session_id_prefix,0, strlen($this->session_id_prefix));

        if(!empty($customer_id)){
            $this->load->model('vsbridge/api');
            $customer_session_id = $this->model_vsbridge_api->getCustomerSessionId($customer_id, $this->store_id);

            if(!empty($customer_session_id['session_id'])){
                return $customer_session_id['session_id'];
            }else{
                $this->model_vsbridge_api->SetCustomerSessionId($customer_id, $this->store_id, $new_session_id);
                return $new_session_id;
            }
        }else{
            return $new_session_id;
        }
    }

    /* Validate customer token */
    protected function validateCustomerToken($token){
        $this->load->model('vsbridge/api');
        $this->load->language('vsbridge/api');
        $token_info = $this->model_vsbridge_api->getCustomerToken($token);

        if(!empty($token_info['timestamp']) && !empty($token_info['customer_id'])){

            $token_age = time() - ((int) $token_info['timestamp']);

            /* Set this to change token lifetime in seconds */
            $token_lifetime = 3600; // Default: 1 hour

            if($token_age <= $token_lifetime){

                $this->load->model('account/customer');
                $customer_info = $this->model_account_customer->getCustomer($token_info['customer_id']);

                return $customer_info;

            }else{
                $this->code = 401;
                $this->result = array(
                    "code" => $this->code,
                    "error" => $this->language->get('error_token_expired')
                );
                $this->sendResponse();
            }

        }else{
            $this->code = 401;
            $this->result = array(
                "code" => $this->code,
                "error" => $this->language->get('error_invalid_token')
            );
            $this->sendResponse();
        }
    }

    protected function validateCartId($cart_id, $token = null){
        if(substr($cart_id, 0, 3 ) == $this->session_id_prefix){
            if(!empty($token)) {
                if ($customer_info = $this->validateCustomerToken($token)) {
                    $this->loadSession($this->getSessionId($customer_info['customer_id']));
                    return $customer_info;
                }
            }else{
                $this->loadSession($cart_id);
                return true;
            }
        }else{
            $this->load->language('vsbridge/api');
            $this->code = 500;
            $this->result = $this->language->get('error_invalid_cart_id');
            $this->sendResponse();
        }
    }

    protected function loadSession($session_id){
        session_abort();
        session_id($session_id);
        session_start();
        $this->session->start('default', $session_id);

        /* Reload the built-in classes to load user session */
        $this->session->data['language'] = $this->config->get('config_language');
        $this->session->data['currency'] = $this->config->get('config_currency');

        $language = new Language($this->config->get('config_language'));
        $language->load($this->config->get('config_language'));

        if ($this->customer->isLogged()) {
            $this->config->set('config_customer_group_id', $this->customer->getGroupId());
        }elseif (isset($this->session->data['guest']) && isset($this->session->data['guest']['customer_group_id'])) {
            $this->config->set('config_customer_group_id', $this->session->data['guest']['customer_group_id']);
        }

        $this->registry->set('language', $language);
        $this->registry->set('currency', new Cart\Currency($this->registry));
        $this->registry->set('customer', new Cart\Customer($this->registry));
        $this->registry->set('cart', new Cart\Cart($this->registry));
        $this->registry->set('tax', new Cart\Tax($this->registry));

        if (isset($this->session->data['shipping_address'])) {
            $this->tax->setShippingAddress($this->session->data['shipping_address']['country_id'], $this->session->data['shipping_address']['zone_id']);
        } elseif ($this->config->get('config_tax_default') == 'shipping') {
            $this->tax->setShippingAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
        }

        if (isset($this->session->data['payment_address'])) {
            $this->tax->setPaymentAddress($this->session->data['payment_address']['country_id'], $this->session->data['payment_address']['zone_id']);
        } elseif ($this->config->get('config_tax_default') == 'payment') {
            $this->tax->setPaymentAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
        }

        $this->tax->setStoreAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
    }
}
