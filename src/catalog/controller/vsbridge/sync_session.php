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

        // to: GET parameter determining the redirection destination
        // any other GET parameter will also be piped
        $to = 'checkout/cart';
        $get_params = [];

        foreach($this->request->get as $k => $v) {
            if ($k == 'to') {
                $to = $v;
            } elseif(!in_array($k, array('route', 'session_id'))) {
                $get_params[] = $k . '=' . $v;
            }
        }

        $params = implode('&', $get_params);

        $this->response->redirect($this->url->link($to, $params));
    }
}