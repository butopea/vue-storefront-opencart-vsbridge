<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeAttributes extends VsbridgeController{

    /*
     * GET /vsbridge/attributes/index
     * This method is used to get all of the attributes from OpenCart.
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
            // To avoid the conflict of attribute IDs and filter IDs, an offset of 10000 is added to attribute IDs and attribute group IDs
            $attribute['attribute_id'] = ((int) $attribute['attribute_id']) + 10000;
            $attribute['attribute_group_id'] = ((int) $attribute['attribute_group_id']) + 10000;


            // We've also added an attribute_group_id field to accompany the /groups endpoint below
            array_push($response, array(
                'attribute_code' => 'attribute_'.$attribute['attribute_id'],
                'frontend_input' => 'text',
                'frontend_label' => $attribute['name'],
                'default_frontend_label' => $attribute['name'],
                'is_user_defined' => true,
                'is_unique' => false,
                'attribute_id' => $attribute['attribute_id'],
                'is_visible' => true,
                'is_comparable' => true,
                'is_visible_on_front' => true,
                'position' => 0,
                'id' => $attribute['attribute_id'],
                'options' => array(),
                'attribute_group_id' => (int) $attribute['attribute_group_id']
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
                'position' => (int) $filter_group['sort_order'],
                'id' => (int) $filter_group['filter_group_id'],
                'options' => $options
            ));
        }

        $this->result = $response;

        $this->sendResponse();
    }

    /*
     * [Note: This endpoint is custom-made and is not required by Vue Storefront.]
     *
     * GET /vsbridge/attributes/groups
     * This method is used to get all of the attribute groups in OpenCart.
     *
     * GET PARAMS:
     * apikey - authorization key provided by /vsbridge/auth/admin endpoint
     */
    public function groups() {
        $this->validateToken($this->getParam('apikey'));

        $language_id = $this->language_id;

        $this->load->model('vsbridge/api');

        $response = array();

        // Retrieve the attribute groups
        $attribute_groups = $this->model_vsbridge_api->getAttributeGroups($language_id);

        foreach($attribute_groups as $attribute_group){

            // To avoid the conflict of attribute group IDs and filter group IDs, an offset of 10000 is added
            $attribute_group['attribute_group_id'] = ((int) $attribute_group['attribute_group_id']) + 10000;

            array_push($response, array(
                'frontend_label' => $attribute_group['name'],
                'is_visible' => true,
                'position' => (int) $attribute_group['sort_order'],
                'id' => $attribute_group['attribute_group_id'],
                'attribute_group_id' => $attribute_group['attribute_group_id']
            ));
        }

        $this->result = $response;

        $this->sendResponse();
    }
}
