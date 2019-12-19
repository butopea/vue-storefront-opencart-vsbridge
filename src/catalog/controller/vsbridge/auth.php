<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeAuth extends VsbridgeController{

    /*
     * POST /vsbridge/auth/admin
     * This method is used to get the admin user token used for subsequent catalog request authorization.
     *
     * POST PARAMS:
     * username - OC API name
     * password - OC API key
     *
     */

    public function admin(){
        $post = $this->getPost();

        if(!empty($post['username']) && !empty($post['password'])){

            /* Retrieve OpenCart API */
            $this->load->model('account/api');
            $api_info = $this->model_account_api->getApiByKey($post['password']);

            /* Authenticate username and password */
            if($this->checkIndexValue($api_info, 'name', $post['username'])){

                /* Check if the IP is whitelisted */
                $whitelisted_ips = $this->model_account_api->getApiIps($api_info['api_id']);
                $client_ip = $this->getClientIp();
                $whitelisted = false;

                foreach($whitelisted_ips as $whitelisted_ip){
                    if($this->checkIndexValue($whitelisted_ip, 'ip', $client_ip, 'trim')){
                        $whitelisted = true;
                    }
                }

                if($whitelisted == true){

                    /* Create a new session ID and store it in OC API */
                    $session_id = $this->session->createId();
                    $this->session->start('api', $session_id);
                    $this->session->data['api_id'] = $api_info['api_id'];

                    $oc_token = $this->model_account_api->addApiSession($api_info['api_id'], $session_id, $client_ip);

                    /* Generate a JWT token based on the OC token */
                    $this->result = $this->getToken($oc_token);

                }else{
                    $this->code = 401;
                    $this->result = "Authentication failed. Your IP (".$client_ip.") is not whitelisted.";
                }

            }else{
                $this->code = 401;
                $this->result = "Authentication failed. Invalid username and/or password.";
            }

        }else{
            $this->code = 400;
            $this->result = "Invalid request. Missing username and/or password.";
        }

        $this->sendResponse();
    }

}