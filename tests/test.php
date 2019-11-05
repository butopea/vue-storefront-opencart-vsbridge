<?php
/*
 * Vue Storefront Integration for OpenCart (AKA VS Bridge)
 * Made with <3 @ Butopea.com
 *
 * API Tests
 * ---------
 * Usage: php test.php
 *
 */

error_reporting(E_ALL & ~E_NOTICE);

use ReallySimpleJWT\Token;

require '../../vendor/autoload.php';

class vsbridge_tests{

    /* Make sure to add a coupon with the code coupon1 */
    /* Remember to install the vsbridge database tables */

    /* ----- !!! API Configuration !!! ----- */
    private $vsbridge_api_base_url = ''; /* Include a trailing slash! */
    private $vsbridge_module_secret_key = ''; /* Generate a secret key in the module settings! */
    private $oc_api_name = ''; /* OpenCart API name */
    private $oc_api_key = ''; /* OpenCart API key */
    private $oc_store_id = 0; /* Store ID (0 for the default store) */
    private $oc_language_id = 2; /* Language ID (can be found in the URL when editing the language in system/localisation/languages) */
    /* ------------------------------------- */

    /* Helper function for displaying test failures */
    function fail($error){
        echo PHP_EOL . $error . PHP_EOL;
        die();
    }

    /* Helper alternative to print_r */
    function debug($input, $raw = null){
        if($raw){
            echo $input.PHP_EOL;
        }else{
            if(is_array($input)){
                echo json_encode($input, JSON_PRETTY_PRINT).PHP_EOL;
            }else{
                echo $input.PHP_EOL;
            }
        }
    }

    /* Helper function for checking if the server response is valid JSON */
    function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /* Helper function for encoding the SKUs */
    function encodeURIComponent($str) {
        $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
        return strtr(rawurlencode($str), $revert);
    }

    /* Helper function for calling the API */
    function request($method, $route, $getparams = null, $postparams = null, $dontfail = null){
        $client = new GuzzleHttp\Client([
            'verify' => false, /* don't verify SSL certificates for dev */
            'headers'  => [
                'content-type' => 'application/json',
                'accept' => '*/*'
            ]
        ]);

        if(!empty($getparams)){
            $getparams = '?'.http_build_query($getparams);
        }

        try{
            $res = $client->request($method, $this->vsbridge_api_base_url.$route.$getparams, ['body' => json_encode($postparams)]);
            if(!empty(trim($res->getBody()))){
                if($this->isJson($res->getBody())){
                    return json_decode($res->getBody(), true);
                }else{
                    return $res->getBody();
                }
            }else{
                if(!$dontfail){
                    $this->fail('Empty response!');
                }
            }
        }catch (Exception $e) {
            if(!$dontfail){
                $this->fail($e->getMessage());
            }
        }

    }

    /* Helper function for validating JWT tokens */
    function validateToken($token){
        if(Token::validate($token, $this->vsbridge_module_secret_key)){
            return true;
        }else{
            $this->fail('Failed to validate the JWT token.');
        }
    }

    /* POST /vsbridge/auth/admin */
    function auth_admin(){
        /* Generate a JWT token */
        $response = $this->request('POST', 'auth/admin', null, array(
            'username' => $this->oc_api_name,
            'password' => $this->oc_api_key
        ));

        /* Validate the JWT token against the secret key */
        if($this->validateToken($response['result'])){
            return $response['result'];
        }
    }

    /* GET /vsbridge/attributes/index */
    function attributes_index($token){
        $response = $this->request('GET', 'attributes/index', array(
            'apikey' => $token,
            'store_id' => $this->oc_store_id,
            'language_id' => $this->oc_language_id
        ));

        return $response;
    }

    /* GET /vsbridge/categories/index */
    function categories_index($token){
        $response = $this->request('GET', 'categories/index', array(
            'apikey' => $token,
            'store_id' => $this->oc_store_id,
            'language_id' => $this->oc_language_id
        ));

        return $response;
    }

    /* GET /vsbridge/taxrules/index */
    function taxrules_index($token){
        $response = $this->request('GET', 'taxrules/index', array(
            'apikey' => $token
        ));

        return $response;
    }

    /* GET /vsbridge/products/index */
    function products_index($token){
        $response = $this->request('GET', 'products/index', array(
            'apikey' => $token,
            'store_id' => $this->oc_store_id,
            'language_id' => $this->oc_language_id,
            'pageSize' => 25,
            'page' => false
        ));

        return $response;
    }

    /* POST /vsbridge/user/create */
    public function user_create($input, $dontfail = null){
        $response = $this->request('POST', 'user/create', null, $input, $dontfail);

        return $response;
    }

    /* POST /vsbridge/user/login */
    public function user_login($input){
        $response = $this->request('POST', 'user/login', null, $input);

        return $response;
    }

    /* POST /vsbridge/user/refresh */
    public function user_refresh($input){
        $response = $this->request('POST', 'user/refresh', null, $input);

        return $response;
    }

    /* POST /vsbridge/user/resetPassword */
    public function user_resetPassword($input){
        $response = $this->request('POST', 'user/resetPassword', null, $input);

        return $response;
    }

    /* POST /vsbridge/user/changePassword */
    public function user_changePassword($token, $input){
        $response = $this->request('POST', 'user/changePassword', array('token' => $token), $input);

        return $response;
    }

    /* GET /vsbridge/user/order-history */
    public function user_orderHistory($token){
        $response = $this->request('GET', 'user/order-history', array('token' => $token));

        return $response;
    }

    /* GET /vsbridge/user/me */
    public function user_me_get($token){
        $response = $this->request('GET', 'user/me', array('token' => $token));

        return $response;
    }

    /* POST /vsbridge/user/me */
    public function user_me_post($token, $input){
        $response = $this->request('POST', 'user/me', array('token' => $token), $input);

        return $response;
    }

    /* POST /vsbridge/cart/create */
    /* For authenticated customers */
    public function cart_create($token){
        $response = $this->request('POST', 'cart/create', array('token' => $token), null);

        return $response;
    }

    /* POST /vsbridge/cart/create */
    /* For guests */
    public function cart_create_guest(){
        $response = $this->request('POST', 'cart/create', null, null);

        return $response;
    }

    /* GET /vsbridge/cart/pull */
    public function cart_pull($cart_id, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('POST', 'cart/pull', $get_params, null);

        return $response;
    }

    /* POST /vsbridge/cart/update */
    public function cart_update($cart_id, $input, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('POST', 'cart/update', $get_params, $input);

        return $response;
    }

    /* POST /vsbridge/cart/delete */
    public function cart_delete($cart_id, $input, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('POST', 'cart/delete', $get_params, $input);

        return $response;
    }

    /* POST /vsbridge/cart/apply-coupon */
    public function cart_apply_coupon($cart_id, $coupon, $token = null){
        $get_params = array('cartId' => $cart_id, 'coupon' => $coupon);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('POST', 'cart/apply-coupon', $get_params);

        return $response;
    }

    /* POST /vsbridge/cart/delete-coupon */
    public function cart_delete_coupon($cart_id, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('POST', 'cart/delete-coupon', $get_params);

        return $response;
    }

    /* GET /vsbridge/cart/coupon */
    public function cart_coupon($cart_id, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('GET', 'cart/coupon', $get_params);

        return $response;
    }

    /* GET /vsbridge/cart/totals */
    public function cart_totals($cart_id, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('GET', 'cart/totals', $get_params);

        return $response;
    }

    /* GET /vsbridge/cart/payment-methods */
    public function payment_methods($cart_id, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('GET', 'cart/payment-methods', $get_params);

        return $response;
    }

    /* POST /vsbridge/cart/shipping-methods */
    public function shipping_methods($cart_id, $input, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('POST', 'cart/shipping-methods', $get_params, $input);

        return $response;
    }

    /* POST /vsbridge/cart/shipping-information */
    public function shipping_information($cart_id, $input, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('POST', 'cart/shipping-information', $get_params, $input);

        return $response;
    }

    /* POST /vsbridge/cart/collect-totals */
    public function collect_totals($cart_id, $input, $token = null){
        $get_params = array('cartId' => $cart_id);

        if($token){
            $get_params['token'] = $token;
        }

        $response = $this->request('POST', 'cart/collect-totals', $get_params, $input);

        return $response;
    }

    /* GET /vsbridge/stock/check */
    public function stock_check($sku){
        $response = $this->request('GET', 'stock/check', array('sku' => $sku));

        return $response;
    }

    /* GET /vsbridge/stock/list */
    public function stock_list($skus){
        $response = $this->request('GET', 'stock/list', array('skus' => $skus));

        return $response;
    }

    /* POST /vsbridge/order/create */
    public function order_create($input){
        $response = $this->request('POST', 'order/create', null, $input);

        return $response;
    }

}

/* ----------------- */
/* Run all the tests */
/* ----------------- */

$vsbridge = new vsbridge_tests();

$admin_token = $vsbridge->auth_admin();

$vsbridge->attributes_index($admin_token);

$vsbridge->categories_index($admin_token);

$vsbridge->taxrules_index($admin_token);

$vsbridge->products_index($admin_token);

// The rest of the tests are already written, but I need to rewrite them to apply for OC Vanilla database

/* If the script reaches here, all tests have been accomplished! */
echo "Congratulations! All tests completed successfully." . PHP_EOL;