<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

class ControllerVsbridgeProducts extends VsbridgeController{

    private $error = array();

    /*
     * GET /vsbridge/products/index
     * This method is used to get all the products from the backend.
     *
     * GET PARAMS:
     * apikey - authorization key provided by /vsbridge/auth/admin endpoint
     * pageSize - number of records to be returned
     * page - number of current page
     *
     * Note:
     *   All products are assumed to be "simple" for now. That is, none have configurable options/variants.
     *   See https://github.com/DivanteLtd/vue-storefront/blob/master/core/modules/catalog/types/Product.ts for fields (not all are necessary though).
     *   Don't feed resized images. Vue storefront has its own image manipulation method.
     *   Visibility: https://magento.stackexchange.com/questions/171584/magento-2-table-name-for-product-visibility
     *
     * TODO:
     *   Implement required fields
     *   Set price according to customer group (i.e. per store)
     *
     * X = not implemented, * = implemented, ?* = implemented but unsure of value
     * "standardSystemFields": [
          * "description",
          X "configurable_options",
          X "tsk",
          * "custom_attributes",
          X "size_options",
          * "regular_price",
          X "final_price",
          * "price",
          X "color_options",
          * "id",
          X "links",
          X "gift_message_available",
          * "category_ids",
          * "sku",
          * "stock",
          * "image",
          * "thumbnail",
          * "visibility",
          * "type_id",
          * "tax_class_id",
          * "media_gallery",
          X "url_key",
          X "max_price",
          X "minimal_regular_price",
          X "special_price",
          X "minimal_price",
          * "name",
          X "configurable_children",
          X "max_regular_price",
          * "category",
          * "status",
          ?* "priceTax",
          ?* "priceInclTax",
          ?* "specialPriceTax",
          ?* "specialPriceInclTax",
          X "_score",
          * "slug",
          X "errors",
          X "info",
          X "erin_recommends",
          X "special_from_date",
          X "news_from_date",
          X "custom_design_from",
          ?* "originalPrice",
          ?* "originalPriceInclTax",
          X "parentSku",
          X "options",
          X "product_option",
          * "qty",
          X "is_configured"
        ]
     */

    public function index(){
        $this->validateToken($this->getParam('apikey'));

        $store_id = $this->store_id;
        $language_id = $this->language_id;

        $pageSize = (int) $this->getParam('pageSize');
        $page = (int) $this->getParam('page');

        $this->load->model('vsbridge/api');
        $this->load->model('catalog/product');

        $filter_data = array(
            'start'                  => ($page - 1) * $pageSize,
            'limit'                  => $pageSize
        );

        $products = $this->model_catalog_product->getProducts($filter_data);

        $response = $this->populateProducts(array(
            'products' => $products,
            'language_id' => $language_id
        ));

        $this->result = $response;

        $this->sendResponse();
    }

    public function slugify($text) {
        // replace non letter or digits by -
        $text = preg_replace('#[^\\pL\d]+#u', '-', $text);

        // trim
        $text = trim($text, '-');

        // transliterate
        if (function_exists('transliterator_transliterate'))
        {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        }

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('#[^-\w]+#', '', $text);

        if (empty($text))
        {
            return 'n-a';
        }

        return $text;
    }

    public function createSlug($product_id, $product_name) {
        $slug = str_replace('/','', parse_url($this->url->link('product/product', 'product_id=' . $product_id))['path']);

        // If there are no SEO slugs available for the product, the resulting $slug will be index.php, which is invalid.
        // Detect if an invalid slug is present and generate a slug manually based on the product name.
        $invalid_slugs = array('index.php');

        if(in_array($slug, $invalid_slugs) || empty($slug)) {
            $slug = $this->slugify($product_name);
        }

        return $slug;
    }

    public function populateProducts($input){
        if(isset($input['products']) && isset($input['language_id'])){
            $products = $input['products'];
            $language_id = $input['language_id'];

            $this->load->model('vsbridge/api');

            $response = array();

            /* There's a bug (no isset check) in seo_url.php line 358 due to MegaFilter's OCMod */
            if(!isset($this->session->data['language'])){
                $this->session->data['language'] = '';
            }

            foreach($products as $product){

                if(isset($product['product_id'])){
                    $product_categories = $this->model_vsbridge_api->getProductCategories($product['product_id']);

                    $adjusted_categories = array();

                    $category_ids = array();

                    foreach($product_categories as $product_category){
                        if(isset($product_category['category_id'])){
                            if($category_details = $this->model_vsbridge_api->getCategoryDetails($product_category['category_id'], $language_id)){
                                array_push($category_ids, (int) $category_details[0]['category_id']);

                                array_push($adjusted_categories, array(
                                    'category_id' => (int) $category_details[0]['category_id'],
                                    'name' => trim($category_details[0]['name'])
                                ));
                            }
                        }
                    }

                    $product_attributes = $this->model_vsbridge_api->getProductAttributes($product['product_id'], $language_id);

                    $custom_attributes = array();

                    $product_filters = $this->model_vsbridge_api->getProductFilters($product['product_id']);

                    $stock = array();

                    if(!empty($product['quantity'])){
                        $stock['is_in_stock'] = true;
                    }else{
                        $stock['is_in_stock'] = false;
                    }

                    $product_images = $this->model_vsbridge_api->getProductImages($product['product_id']);

                    $media_gallery = array();

                    foreach($product_images as $product_image){
                        if(isset($product_image['image'])){
                            array_push($media_gallery, array(
                                'image' => '/'.$product_image['image'],
                                'lab' => '',
                                'pos' => (int) $product_image['sort_order'],
                                'typ' => 'image'
                            ));
                        }
                    }

                    $slug = $this->createSlug($product['product_id'], $product['name']);

                    $original_price_incl_tax = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'), NULL, FALSE);
                    $original_price_excl_tax = $this->currency->format($product['price'], $this->config->get('config_currency'), NULL, FALSE);

                    $special_price_incl_tax = null;
                    $special_price_excl_tax = null;

                    if(!empty($product['special'])){
                        $special_price_incl_tax = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'), NULL, FALSE);
                        $special_price_excl_tax = $this->currency->format($product['special'], $this->config->get('config_currency'), NULL, FALSE);
                    }

                    $market_price_incl_tax = null;
                    $market_price_excl_tax = null;

                    if(!empty($product['recommended_price'])){
                        foreach($product['recommended_price'] as $recommended_price){
                            if($recommended_price['customer_group_id'] == 99){
                                $market_price_incl_tax = $this->currency->format($this->tax->calculate($recommended_price['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'), NULL, FALSE);
                                $market_price_excl_tax = $this->currency->format($recommended_price['price'], $this->config->get('config_currency'), NULL, FALSE);
                            }
                        }
                    }

                    $product_array = array(
                        'id' => (int) $product['product_id'],
                        'type_id' => 'simple',
                        'sku' => $product['sku'],
                        'category' => $adjusted_categories,
                        'category_ids' => $category_ids,
                        'description' => $product['description'],
                        'custom_attributes' => $custom_attributes,
                        'price' =>  $original_price_incl_tax,
                        'final_price' => isset($special_price_incl_tax) ? $special_price_incl_tax : $original_price_incl_tax,
                        'priceInclTax' =>  isset($special_price_incl_tax) ? $special_price_incl_tax : $original_price_incl_tax,
                        'priceTax' => 0,
                        'originalPrice' =>  $original_price_incl_tax,
                        'originalPriceInclTax' =>  $original_price_incl_tax,
                        'specialPriceInclTax' =>  $special_price_incl_tax ?? 0,
                        'specialPriceTax' =>  0,
                        'special_price' =>  $special_price_incl_tax ?? 0,
                        'regular_price' =>  $original_price_incl_tax,
                        'marketPrice' => $market_price_incl_tax ?? 0,
                        'stock' => $stock,
                        'image' => '/'.$product['image'],
                        'thumbnail' => '/'.$product['image'],
                        'visibility' => 4,
                        'tax_class_id' => (int) $product['tax_class_id'],
                        'media_gallery' => $media_gallery,
                        'name' => $product['name'],
                        'status' => (int) $product['status'],
                        'slug' => $slug,
                        'url_path' => $slug,
                        'created_at' => $product['date_added'],
                        'updated_at' => $product['date_modified']
                    );

                    if(!empty($product['quantity'])){
                        if(intval($product['quantity']) > 0){
                            $product_array['qty'] = 1;
                        }else{
                            $product_array['qty'] = 0;
                        }
                    }else{
                        $product_array['qty'] = 0;
                    }

                    foreach(array('length', 'width', 'height', 'weight') as $dimension) {
                        $product_array[$dimension] =  (float) number_format($product[$dimension],2);
                    }

                    $weight_class =  $this->model_vsbridge_api->getWeightClass($product['weight_class_id'], $this->language_id);
                    $length_class = $this->model_vsbridge_api->getLengthClass($product[$dimension.'_class_id'], $this->language_id);

                    $product_array['weight_class'] = isset($weight_class[0]['unit']) ? trim($weight_class[0]['unit']) : '';
                    $product_array['length_class'] = isset($length_class[0]['unit']) ? trim($length_class[0]['unit']) : '';

                    foreach($product_attributes as $product_attribute){
                        if(isset($product_attribute['attribute_id'])){
                            // To avoid the conflict of attribute IDs and filter IDs, an offset of 10000 is added
                            $product_attribute['attribute_id'] = ((int) $product_attribute['attribute_id']) + 10000;
                            $product_array['attribute_'.$product_attribute['attribute_id']] = trim($product_attribute['text']);
                        }
                    }

                    foreach($product_filters as $product_filter){
                        if(isset($product_filter['filter_group_id']) && isset($product_filter['filter_id'])){
                            $product_array['filter_group_'.$product_filter['filter_group_id']] = (int) $product_filter['filter_id'];
                        }
                    }

                    $oc_url_alias = $this->model_vsbridge_api->getUrlAlias('product', $product['product_id']);

                    if(!empty($oc_url_alias['keyword'])){
                        $product_array['slug'] = $oc_url_alias['keyword'];
                        $product_array['url_path'] = $oc_url_alias['keyword'];
                    }

                    // Check for SEO URls via the OpenCart extension [SEO BackPack 2.9.1]
                    $seo_url_alias = $this->model_vsbridge_api->getSeoUrlAlias('product', $product['product_id'], $this->language_id);

                    if(!empty($seo_url_alias['keyword'])){
                        $product_array['slug'] = $seo_url_alias['keyword'];
                        $product_array['url_path'] = $seo_url_alias['keyword'];
                    }

                    // Related products
                    $related_products = $this->model_vsbridge_api->getRelatedProducts($product['product_id']);
                    $related_product_ids = array();

                    if(!empty($related_products)){
                        foreach($related_products as $related_product){
                            if(!empty($related_product['related_id'])){
                                array_push($related_product_ids, $related_product['related_id']);
                            }
                        }
                    }

                    $product_array['related_products'] = $related_product_ids;

                    // Product variants (only if Advanced Product Variants extension is installed)
                    $product_variant_ids = $this->model_vsbridge_api->getProductVariants($product['product_id'], $this->language_id);

                    if(!empty($product_variant_ids)){
                        $product_array['product_variants'] = $product_variant_ids;
                    }

                    // Load product discounts (tier prices)
                    $product_discounts = $this->model_vsbridge_api->getProductDiscounts($product['product_id']);

                    $tier_prices = array();

                    foreach($product_discounts as $product_discount){
                        array_push($tier_prices, array(
                            'customer_group_id' => (int) $product_discount['customer_group_id'],
                            'qty' => (int) $product_discount['quantity'],
                            'value' => $this->currency->format($this->tax->calculate($product_discount['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->config->get('config_currency'), NULL, FALSE)
                        ));
                    }

                    $product_array['tier_prices'] = $tier_prices;

                    // TODO: Change 1 and 0 to true and false when https://github.com/DivanteLtd/vue-storefront/issues/3800 is solved
                    // Add the 'new' label if the product was added 30 days ago or less
                    $time = time();
                    $max_days_for_new = 30;
                    $date1 = strtotime($product['date_available']);
                    $date2 = strtotime($product['date_added']);
                    $is_new = $max_days_for_new > ($time - ($date1 > $date2 ? $date1 : $date2)) / 86400;
                    $product_array['new'] = $is_new ? '1' : '0';

                    // Add the 'sale' label if the product is on sale
                    $product_array['sale'] = isset($special_price_incl_tax) ? '1' : '0';

                    array_push($response, $product_array);
                }
            }

            return $response;
        }else{
            return false;
        }
    }

}
