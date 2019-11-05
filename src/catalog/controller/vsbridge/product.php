<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeProduct extends VsbridgeController{

    private $error = array();

    /*
     * GET /vsbridge/product/list and /vsbridge/product/render-list
     * OpenCart specific methods to return the product details for specified SKUs.
     * Methods are mostly used for data synchronization with OpenCart and for some specific cases when overriding the platform prices inside Vue Storefront.
     *
     * GET PARAMS:
     * skus - comma separated list of skus to get
     *
     * TODO: Implement this feature
     */

    public function __construct($registry){
        parent::__construct($registry);

        $function_name = str_replace('vsbridge/product/', '', $_REQUEST['route']);

        switch($function_name){
            case 'list':
                $this->renderList();
                break;

            case 'render-list':
                $this->renderList();
                break;
        }

        die();
    }

    public function renderList(){
        $skus = $this->getParam('skus');

        $sku_list = explode(',', $skus);

        $products = array();

        $this->load->model('vsbridge/api');

        /*foreach($sku_list as $sku){
            array_push($products, $this->model_vsbridge_api->getProductBySku($sku, 2));
        }*/

        $this->result = array('items' => $products);

        $this->sendResponse();
    }
}