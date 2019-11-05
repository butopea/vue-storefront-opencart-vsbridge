<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeAttributes extends VsbridgeController{

    private $error = array();

    /*
     * GET /vsbridge/attributes/index
     * This method is used to get all the attributes from OpenCart.
     *
     * Following https://github.com/DivanteLtd/vue-storefront-integration-boilerplate/blob/master/1.%20Expose%20the%20API%20endpoints%20required%20by%20VS/Product%20Attributes.md
     *
     * GET PARAMS:
     * apikey - authorization key provided by /vsbridge/auth/admin endpoint
     */

    public function index(){
        $this->validateToken($this->getParam('apikey'));

        $language_id = $this->language_id;

        $this->load->model('vsbridge/api');

        $response = array();

        // Add the actual attributes
        $attributes = $this->model_vsbridge_api->getAttributes($language_id);

        foreach($attributes as $attribute){
            array_push($response, array(
                'attribute_code' => 'attribute_'.$attribute['attribute_id'],
                'frontend_input' => 'text',
                'frontend_label' => $attribute['name'],
                'default_frontend_label' => $attribute['name'],
                'is_user_defined' => true,
                'is_unique' => false,
                'attribute_id' => (int) $attribute['attribute_id'],
                'is_visible' => true,
                'is_comparable' => true,
                'is_visible_on_front' => true,
                'position' => 0,
                'id' => (int) $attribute['attribute_id'],
                'options' => array()
            ));
        }

        // Add filters as hidden, searchable attributes
        $filter_groups = $this->model_vsbridge_api->getFilterGroups($language_id);

        foreach($filter_groups as $filter_group){
            $options = array();

            $filters = $this->model_vsbridge_api->getFilters($filter_group['filter_group_id'], $language_id);

            foreach($filters as $filter){
                array_push($options, array(
                    'value' => (int) $filter['filter_id'],
                    'label' => trim($filter['name'])
                ));
            }

            array_push($response, array(
                'attribute_code' => 'filter_group_'.$filter_group['filter_group_id'],
                'frontend_input' => 'select',
                'frontend_label' => $filter_group['name'],
                'default_frontend_label' => $filter_group['name'],
                'is_user_defined' => true,
                'is_unique' => true,
                'attribute_id' => (int) $filter_group['filter_group_id'],
                'is_visible' => true,
                'is_comparable' => true,
                'is_visible_on_front' => true,
                'position' => 0,
                'id' => (int) $filter_group['filter_group_id'],
                'options' => $options
            ));
        }

        $this->result = $response;

        $this->sendResponse();
    }

}
