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
                $this->list();
                break;

            case 'render-list':
                $this->renderList();
                break;
        }

        die();
    }

    /*
     * RESPONSE:
     * {
          "code": 200,
          "result": {
            "items": [
              {
                "id": 1866,
                "sku": "WP07",
                "name": "Aeon Capri",
                "price": 0,
                "status": 1,
                "visibility": 4,
                "type_id": "configurable",
                "created_at": "2017-11-06 12:17:26",
                "updated_at": "2017-11-06 12:17:26",
                "product_links": [],
                "tier_prices": [],
                "custom_attributes": [
                  {
                    "attribute_code": "description",
                    "value": "<p>Reach for the stars and beyond in these Aeon Capri pant. With a soft, comfortable feel and moisture wicking fabric, these duo-tone leggings are easy to wear -- and wear attractively.</p>\n<p>&bull; Black capris with teal accents.<br />&bull; Thick, 3\" flattering waistband.<br />&bull; Media pocket on inner waistband.<br />&bull; Dry wick finish for ultimate comfort and dryness.</p>"
                  },
                  {
                    "attribute_code": "image",
                    "value": "/w/p/wp07-black_main.jpg"
                  },
                  {
                    "attribute_code": "category_ids",
                    "value": [
                      "27",
                      "32",
                      "35",
                      "2"
                    ]
                  },
                  {
                    "attribute_code": "url_key",
                    "value": "aeon-capri"
                  },
                  {
                    "attribute_code": "tax_class_id",
                    "value": "2"
                  },
                  {
                    "attribute_code": "eco_collection",
                    "value": "0"
                  },
                  {
                    "attribute_code": "performance_fabric",
                    "value": "1"
                  },
                  {
                    "attribute_code": "erin_recommends",
                    "value": "0"
                  },
                  {
                    "attribute_code": "new",
                    "value": "0"
                  },
                  {
                    "attribute_code": "sale",
                    "value": "0"
                  },
                  {
                    "attribute_code": "style_bottom",
                    "value": "107"
                  },
                  {
                    "attribute_code": "pattern",
                    "value": "195"
                  },
                  {
                    "attribute_code": "climate",
                    "value": "205,212,206"
                  }
                ]
              }
            ],
            "search_criteria": {
              "filter_groups": [
                {
                  "filters": [
                    {
                      "field": "sku",
                      "value": "WP07",
                      "condition_type": "in"
                    }
                  ]
                }
              ]
            },
            "total_count": 1
          }
        }
     */
    public function list(){
        $skus = $this->getParam('skus');

        $sku_list = explode(',', $skus);

        $this->load->model('catalog/product');
        $this->load->model('vsbridge/api');

        $products = array();

        foreach($sku_list as $sku){
            $product_id = $this->model_vsbridge_api->getProductIdFromSku($sku);

            if(!empty($product_id)){
                array_push($products,  $this->model_catalog_product->getProduct($product_id['product_id']));
            }
        }

        $populated_products = $this->load->controller('vsbridge/products/populateProducts', array(
            'products' => $products,
            'language_id' => $this->language_id
        ));

        $this->result = array('items' => $populated_products);

        $this->sendResponse();
    }

    /*
     * RESPONSE:
     *
     * {
          "code": 200,
          "result": {
            "items": [
              {
                "price_info": {
                  "final_price": 59.04,
                  "max_price": 59.04,
                  "max_regular_price": 59.04,
                  "minimal_regular_price": 59.04,
                  "special_price": null,
                  "minimal_price": 59.04,
                  "regular_price": 48,
                  "formatted_prices": {
                    "final_price": "<span class=\"price\">$59.04</span>",
                    "max_price": "<span class=\"price\">$59.04</span>",
                    "minimal_price": "<span class=\"price\">$59.04</span>",
                    "max_regular_price": "<span class=\"price\">$59.04</span>",
                    "minimal_regular_price": null,
                    "special_price": null,
                    "regular_price": "<span class=\"price\">$48.00</span>"
                  },
                  "extension_attributes": {
                    "tax_adjustments": {
                      "final_price": 47.999999,
                      "max_price": 47.999999,
                      "max_regular_price": 47.999999,
                      "minimal_regular_price": 47.999999,
                      "special_price": 47.999999,
                      "minimal_price": 47.999999,
                      "regular_price": 48,
                      "formatted_prices": {
                        "final_price": "<span class=\"price\">$48.00</span>",
                        "max_price": "<span class=\"price\">$48.00</span>",
                        "minimal_price": "<span class=\"price\">$48.00</span>",
                        "max_regular_price": "<span class=\"price\">$48.00</span>",
                        "minimal_regular_price": null,
                        "special_price": "<span class=\"price\">$48.00</span>",
                        "regular_price": "<span class=\"price\">$48.00</span>"
                      }
                    },
                    "weee_attributes": [],
                    "weee_adjustment": "<span class=\"price\">$59.04</span>"
                  }
                },
                "url": "http://demo-magento2.vuestorefront.io/aeon-capri.html",
                "id": 1866,
                "name": "Aeon Capri",
                "type": "configurable",
                "store_id": 1,
                "currency_code": "USD",
                "sgn": "bCt7e44sl1iZV8hzYGioKvSq0EdsAcF21FhpTG5t8l8"
              }
            ]
          }
        }
     */
    public function renderList(){
        $skus = $this->getParam('skus');
        $customer_group_id = $this->getParam('customerGroupId', true) ?? $this->config->get('config_customer_group_id');

        $sku_list = explode(',', $skus);

        $this->load->model('catalog/product');
        $this->load->model('vsbridge/api');

        $products = array();

        foreach($sku_list as $sku){
            $product_id = $this->model_vsbridge_api->getProductIdFromSku($sku);

            if(!empty($product_id)){
                array_push($products,  $this->model_catalog_product->getProduct($product_id['product_id']));
            }
        }

        $populated_products = $this->load->controller('vsbridge/products/populateProducts', array(
            'products' => $products,
            'language_id' => $this->language_id
        ));

        /* Adjust the output to reflect the format above */
        $adjusted_products = array();

        foreach($populated_products as $populated_product){
            $customer_group_prices = $this->model_vsbridge_api->getProductDiscountsByCustomerGroup($populated_product['id'], $customer_group_id);

            if(!empty($customer_group_prices[0]['price'])){
                $new_final_price = $this->currency->format($this->tax->calculate($customer_group_prices[0]['price'], $populated_product['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'), NULL, FALSE);
            }

            $populated_product['price_info'] = array(
                'final_price' => $new_final_price ?? $populated_product['final_price'],
                'extension_attributes' => array(
                    'tax_adjustments' => array(
                        'final_price' => $new_final_price ?? $populated_product['final_price']
                    )
                )
            );

            array_push($adjusted_products, $populated_product);
        }


        $this->result = array('items' => $adjusted_products);

        $this->sendResponse();
    }
}