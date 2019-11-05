<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeSyncSession extends VsbridgeController{

    /* In order for all the Vue-Storefront cart information be accessible in browser, we need to load the VS Bridge session into the browser */
    public function index(){
        $vsbridge_session_id = $this->getParam('session_id');

        session_abort();
        session_id($vsbridge_session_id);
        session_start();
        $this->session->start('default', $vsbridge_session_id);

        $this->response->redirect($this->url->link('checkout/cart'));
    }
}