<?php

require_once(DIR_SYSTEM . 'engine/vsbridgecontroller.php');

use voku\helper\URLify;

class ControllerVsbridgeCategories extends VsbridgeController{

    private $flattenedIds = array();
    private $flattenedElements = array();
    /*
     * GET /vsbridge/categories/index
     * This method is used to get all the categories from OpenCart.
     *
     * Following https://github.com/DivanteLtd/vue-storefront/blob/master/docs/guide/data/data.md
     *
     * GET PARAMS:
     * apikey - authorization key provided by /vsbridge/auth/admin endpoint
     * pageSize - number of records to be returned
     * page - number of current page
     *
     * Note: Category ID 1 is RESERVED as the base category ID.
     *
     * TODO: Paginate
     */

    public function index(){
        $this->validateToken($this->getParam('apikey'));

        $store_id = $this->store_id;
        $language_id = $this->language_id;

        $this->load->model('vsbridge/api');

        $categories = $this->model_vsbridge_api->getCategories($language_id, $store_id);

        $categories_tree = $this->buildTree($categories);

        $this->mapFields($categories_tree);

        $this->flattenCopy($categories_tree);

        $categories_tree = array_merge($categories_tree, $this->flattenedElements);

        $this->result = $categories_tree;

        $this->sendResponse();
    }

    public function buildTree(array &$elements, $parentId = 1) {
        $branch = array();

        foreach ($elements as &$element) {
            if((int) $element['parent_id'] == 0){
              $element['parent_id'] = 1;
            }

            if ((int)$element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, (int)$element['category_id']);
                if ($children) {
                    $element['children_data'] = $children;
                }

                array_push($branch, $element);
                unset($element);
            }
        }

        return $branch;
    }

    public function mapField(array &$array, $oldkey, $newkey, $binary_to_truthy = null){
        if(isset($array[$oldkey])){
            if(!empty($binary_to_truthy)){
                if($array[$oldkey] == 1){
                    $array[$newkey] = true;
                }else{
                    $array[$newkey] = false;
                }
            }else{
                $array[$newkey] = $array[$oldkey];
            }
            unset($array[$oldkey]);
        }
    }

    public function filterFields(array &$array, array $fields){
        $new_array = array();

        foreach($fields as $field){
            if(isset($array[$field])){
                $new_array[$field] = $array[$field];
            }
        }

        return $new_array;
    }

    public function toInt(array &$array, $index){
        if(isset($array[$index])){
            $array[$index] = (int) $array[$index];
        }
    }

    // Note: OpenCart does not have slugs for categories. That's why we generate one based on the category name and ID.
    // If your implementation uses a custom SEO extension, modify the mapFields function to reflect the correct slug and URL key/path.
    public function mapFields(array &$elements, $current_level = 0, $id_path = array(), $name_path = array()){
        foreach ($elements as $element_key => &$element) {
            if((int) $element['parent_id'] == 1){
              $current_level = 0;
              $id_path = array();
              $name_path = array();
            }

            $this->mapField($element, 'category_id', 'id');
            $this->mapField($element, 'status', 'is_active', true);
            $this->mapField($element, 'date_added', 'created_at');
            $this->mapField($element, 'date_modified', 'updated_at');

            $this->toInt($element, 'id');
            $this->toInt($element, 'parent_id');

            $this->load->model('vsbridge/api');
            $element['product_count'] = (int) $this->model_vsbridge_api->countCategoryProducts($element['id']);

            $name_slug = mb_strtolower(URLify::filter($element['name'], 60, $this->language_code));

            $element['path'] = implode('/', array_merge($id_path, array($element['id'])));

            $element['slug'] = $name_slug.'-'.$element['id'];

            // Change if you use a custom SEO extension
            $element['url_key'] = trim($element['slug']);
            $element['url_path'] = trim(implode('/', array_merge($name_path, array($name_slug))).'-'.$element['id']);

            // Check for SEO URls via the OpenCart extension [SEO BackPack 2.9.1]
            $seo_url_alias = $this->model_vsbridge_api->getSeoUrlAlias('category', $element['id'], $this->language_id);

            if(!empty($seo_url_alias['keyword'])){
              $element['url_path'] = trim($seo_url_alias['keyword']);
            }

            $element['level'] = $current_level + 1;
            $element['position'] = (int) $element_key;

            $element = $this->filterFields($element, array(
                'id',
                'parent_id',
                'name',
                'is_active',
                'position',
                'level',
                'product_count',
                'children_data',
                'children_count',
                'created_at',
                'updated_at',
                'path',
                'slug',
                'url_key',
                'url_path'
            ));

            if(!empty($element['children_data'])){
                $element['children_count'] = count($element['children_data']);
                $this->mapFields($element['children_data'], $current_level+1, array_merge($id_path, array($element['id'])), array_merge($name_path, array($name_slug)));
            }else{
                $element['children_count'] = 0;
                $element['children_data'] = array();
            }
        }

        unset($element);
    }

    public function flattenCopy(array &$elements){
      foreach($elements as &$element){
        if(!in_array($element['id'], $this->flattenedIds)){

          array_push($this->flattenedElements, $element);
          array_push($this->flattenedIds, $element['id']);
        }

        if(!empty($element['children_data'])){
          $this->flattenCopy($element['children_data']);
        }
      }
    }

}
