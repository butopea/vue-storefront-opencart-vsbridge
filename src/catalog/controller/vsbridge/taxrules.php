<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeTaxrules extends VsbridgeController{

    private $error = array();

    /*
     * GET /vsbridge/taxrules/index
     * This method is used to get all the tax rules from the backend.
     *
     * GET PARAMS:
     * apikey - authorization key provided by /vsbridge/auth/admin endpoint
     * pageSize - number of records to be returned
     * page - number of current page
     *
     * Note: customer_tax_class_ids is not implemented in Vue Storefront yet and also does not exist in OpenCart.
     *       Instead, there is a tax_rate_to_customer_group table to link customer groups with different tax rates.
     *       Vue Storefront only accepts percentage tax rates.
     * TODO: Paginate
     */

    public function index(){
        $this->validateToken($this->getParam('apikey'));

        $this->load->model('vsbridge/api');

        $response = array();

        $tax_rules = $this->model_vsbridge_api->getTaxRules();

        foreach($tax_rules as $tax_rule){
            if(!empty($tax_rule['tax_rate_id']) && !empty($tax_rule['tax_class_id'])){
                $tax_rate = $this->model_vsbridge_api->getTaxRates($tax_rule['tax_rate_id'])[0];
                $tax_class = $this->model_vsbridge_api->getTaxClasses($tax_rule['tax_class_id'])[0];
                if(!empty($tax_rate) && !empty($tax_class)){
                    if($this->checkIndexValue($tax_rate, 'type','P', 'trim')){
                        array_push($response, array(
                            'id' => (int) $tax_rule['tax_rule_id'],
                            'code' => $tax_class['title'],
                            'priority' => (int) $tax_rule['priority'],
                            'product_tax_class_ids' => array((int) $tax_rule['tax_class_id']),
                            'rates' => array(
                                array(
                                    'id' => (int) $tax_rate['tax_rate_id'],
                                    'tax_country_id' => (int) $tax_rate['geo_zone_id'],
                                    'code' => $tax_rate['name'],
                                    'rate' => (float) $tax_rate['rate'],
                                )
                            )
                        ));
                    }
                }
            }
        }

        $this->result = $response;

        $this->sendResponse();
    }
}