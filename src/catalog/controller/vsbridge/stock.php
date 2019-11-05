<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeStock extends VsbridgeController{

    private $error = array();

    public function __construct($registry){
        parent::__construct($registry);

        $function_name = str_replace('vsbridge/stock/', '', $_REQUEST['route']);

        /* Using a different name for the stock/list endpoint since list is a native PHP function */
        switch($function_name){
            case 'list':
                $this->sku_list();
                break;
        }
    }

    /*
     * GET /vsbridge/stock/check/:sku
     * This method is used to check the stock item for specified product sku
     *
     * Note: Currently using the endpoint that appears in vue-storefront/core/modules/catalog/store/stock/actions.ts as mentioned above.
     */

    public function check(){
        $sku = urldecode($this->getParam('sku'));

        $this->load->model('vsbridge/api');

        $product_info = $this->model_vsbridge_api->getProductBySku($sku, $this->language_id);

        if(!empty($product_info)){

            $adjusted_product_info = array(
                'item_id' => (int) $product_info['product_id'],
                'product_id' => (int) $product_info['product_id'],
                'qty' => (int) $product_info['quantity'],
                'is_in_stock' => !empty($product_info['quantity'])
            );

            $this->result = $adjusted_product_info;

        }else{
            $this->load->language('vsbridge/api');
            $this->code = 500;
            $this->result = $this->language->get('error_product_not_found');
        }

        $this->sendResponse();
    }

    /*
     * GET /vsbridge/stock/list
     * This method is used to check multiple stock items for specified product skus.
     *
     * GET PARAMS:
     * skus - param of comma-separated values to indicate which stock items to return.
     */

    public function sku_list(){
        $skus = explode(',', urldecode($this->getParam('skus')));

        $this->load->model('vsbridge/api');

        $adjusted_product_info_list  = array();

        foreach($skus as $sku){
            $product_info = $this->model_vsbridge_api->getProductBySku($sku, $this->language_id);

            if(!empty($product_info)){
                array_push($adjusted_product_info_list, array(
                    'item_id' => (int) $product_info['product_id'],
                    'product_id' => (int) $product_info['product_id'],
                    'qty' => (int) $product_info['quantity'],
                    'is_in_stock' => !empty($product_info['quantity'])
                ));
            }
        }

        if(!empty($adjusted_product_info_list)){
            $this->result = $adjusted_product_info_list;
        }else{
            $this->load->language('vsbridge/api');
            $this->code = 500;
            $this->result = $this->language->get('error_product_not_found');
        }

        $this->sendResponse();
    }
}