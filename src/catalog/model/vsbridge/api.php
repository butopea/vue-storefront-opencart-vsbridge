<?php
class ModelVsbridgeApi extends Model {
    public function getApiSession($token) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_session` WHERE `token` = '" . $this->db->escape($token) . "'");

        return $query->row;
    }

    public function getAttributes($language_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "attribute_description` ad INNER JOIN `" . DB_PREFIX . "attribute` a ON (a.attribute_id = ad.attribute_id) WHERE `language_id` = '".(int) $language_id."'");

        return $query->rows;
    }

    public function getAttributeGroups($language_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "attribute_group_description` agd INNER JOIN `" . DB_PREFIX . "attribute_group` ag ON (ag.attribute_group_id = agd.attribute_group_id) WHERE `language_id` = '".(int) $language_id."'");

        return $query->rows;
    }

    public function getFilters($filter_group_id, $language_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "filter_description` fd INNER JOIN `" . DB_PREFIX ."filter` f ON (f.filter_id = fd.filter_id) WHERE fd.`filter_group_id` = '".(int) $filter_group_id."' AND `language_id` = '".(int) $language_id."'");

        return $query->rows;
    }

    public function getFilterGroups($language_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "filter_group_description` fgd INNER JOIN `" . DB_PREFIX ."filter_group` fg ON (fg.filter_group_id = fgd.filter_group_id) WHERE `language_id` = '".(int) $language_id."'");

        return $query->rows;
    }

    public function getCategories($language_id, $store_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_description` AS tcatdesc INNER JOIN `" . DB_PREFIX . "category` AS tcat ON tcatdesc.category_id = tcat.category_id WHERE tcatdesc.category_id IN (SELECT category_id FROM `" . DB_PREFIX . "category_to_store` WHERE `store_id` = '".(int) $store_id."') AND `language_id` = '".(int) $language_id."'");

        return $query->rows;
    }

    // OpenCart Extension [Seo BackPack 2.9.1]
    public function getSeoUrlAlias($type, $type_id, $language_id){
        $check_table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "seo_url_alias'");

        if ($check_table->num_rows) {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url_alias` WHERE `query` = '" . $this->db->escape($type) . "_id=" . (int)$type_id . "' AND `language_id` = '" . (int)$language_id . "' ORDER BY `id` DESC");
            return $query->row;
        }else{
            return false;
        }
    }

    // Native OpenCart URL aliases (doesn't support multiple languages)
    public function getUrlAlias($type, $type_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "url_alias` WHERE `query` = '".$this->db->escape($type)."_id=".(int) $type_id."' ORDER BY `url_alias_id` DESC");

        return $query->row;
    }

    public function getStoreConfig($config_name, $store_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '".(int) $store_id."' AND `key` = '".$this->db->escape($config_name)."'");

        return $query->row;
    }

    public function countCategoryProducts($category_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_to_category` WHERE `category_id` = '".(int) $category_id."'");

        return $query->num_rows;
    }

    public function getTaxRules($tax_rule_id = null){
        $tax_rule_id_check = "";

        if(isset($tax_rule_id)){
            $tax_rule_id_check = " WHERE `tax_rule_id` = '".(int) $tax_rule_id."'";
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "tax_rule`".$tax_rule_id_check);

        return $query->rows;
    }

    public function getTaxRates($tax_rate_id = null){
        $tax_rate_id_check = "";

        if(isset($tax_rate_id)){
            $tax_rate_id_check = " WHERE `tax_rate_id` = '".(int) $tax_rate_id."'";
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "tax_rate`".$tax_rate_id_check);

        return $query->rows;
    }

    public function getTaxClasses($tax_class_id = null){
        $tax_class_id_check = "";

        if(isset($tax_class_id)){
            $tax_class_id_check = " WHERE `tax_class_id` = '".(int) $tax_class_id."'";
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "tax_class`".$tax_class_id_check);

        return $query->rows;
    }

    public function getProducts($language_id, $store_id, $pageSize, $page){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_description` AS tproddesc INNER JOIN `" . DB_PREFIX . "product` AS tprod ON tproddesc.product_id = tprod.product_id WHERE tproddesc.product_id IN (SELECT product_id FROM `" . DB_PREFIX . "product_to_store` WHERE `store_id` = '".(int) $store_id."') AND tproddesc.language_id = '".(int) $language_id."' AND tprod.status = '1' ORDER BY tprod.product_id ASC LIMIT ".(int) ($page * $pageSize).",".(int) $pageSize);

        return $query->rows;
    }

    public function getProductSpecialPrice($product_id, $group_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_special` WHERE `product_id` = '" . (int) $product_id . "' AND customer_group_id = '" . (int)$group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW()))");

        return $query->row;
    }

    public function getProductCategories($product_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '".(int) $product_id."'");

        return $query->rows;
    }

    public function getProductLayoutName($product_id, $store_id = 0){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_to_layout` LEFT JOIN `" . DB_PREFIX . "layout` ON `" . DB_PREFIX . "product_to_layout`.`layout_id` = `" . DB_PREFIX . "layout`.`layout_id` WHERE `" . DB_PREFIX . "product_to_layout`.`product_id` = '" . (int)$product_id . "' AND `" . DB_PREFIX . "product_to_layout`.`store_id` = '" . (int)$store_id. "'");

        $product_layout_name = 'default';

        if ($query->num_rows && $query->row['name']) {
            $product_layout_name = $query->row['name'];
        }

        return $product_layout_name;
    }

    // For use with the Advanced Product Variant extension
    public function getProductVariants($product_id, $language_id){
        $check_table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "variantproducts'");

        if ($check_table->num_rows) {

            $query = $this->db->query("
					SELECT vd.title, (SELECT GROUP_CONCAT(v2p.product_id) FROM " . DB_PREFIX . "variantproducts_to_product v2p WHERE v.variantproduct_id = v2p.variantproduct_id ) prodIds
					FROM " . DB_PREFIX . "variantproducts v
					LEFT JOIN " . DB_PREFIX . "variantproducts_description vd ON (v.variantproduct_id = vd.variantproduct_id)
					LEFT JOIN " . DB_PREFIX . "variantproducts_to_product v2p ON (v.variantproduct_id = v2p.variantproduct_id)
					WHERE
					    v2p.product_id = '" . (int)$product_id . "' AND
					    vd.language_id = '" . (int)$language_id . "' AND
					    v.status = '1'
					ORDER BY v.sort_order, v.variantproduct_id ASC
					");

            $product_variant_ids = array();

            foreach($query->rows as $product_variants){
                if(!empty($product_variants['prodIds'])){
                    $product_ids = explode(',', $product_variants['prodIds']);

                    if(is_array($product_ids)){
                        foreach($product_ids as $pid){
                            if(!in_array($pid, $product_variant_ids) && $pid != $product_id){
                                array_push($product_variant_ids, $pid);
                            }
                        }
                    }
                }
            }

            return $product_variant_ids;
        }else{
            return false;
        }
    }

    public function getCategoryDetails($category_id, $language_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_description` WHERE category_id = '".(int) $category_id."' AND language_id = '".(int) $language_id."'");

        return $query->rows;
    }

    public function getProductAttributes($product_id, $language_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_attribute` WHERE product_id = '".(int) $product_id."' AND language_id = '".(int) $language_id."'");

        return $query->rows;
    }

    public function getProductFilters($product_id){
        $query = $this->db->query("SELECT pft.filter_id, ft.filter_group_id FROM `". DB_PREFIX ."product_filter` pft LEFT JOIN `". DB_PREFIX ."filter` ft ON ft.filter_id = pft.filter_id WHERE pft.product_id = '".(int) $product_id."'");

        return $query->rows;
    }

    public function getRelatedProducts($product_id){
        $query = $this->db->query("SELECT DISTINCT related_id FROM `". DB_PREFIX ."product_related` WHERE product_id = '".(int) $product_id."'");

        return $query->rows;
    }

    public function getProductDiscountsByCustomerGroup($product_id, $customer_group_id){
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$customer_group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity ASC, priority ASC, price ASC");

        return $query->rows;
    }

    public function getProductDiscounts($product_id){
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity ASC, priority ASC, price ASC");

        return $query->rows;
    }

    public function getProductImages($product_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_image` WHERE product_id = '".(int) $product_id."' ORDER BY sort_order ASC");

        return $query->rows;
    }

    public function getProductBySku($sku, $language_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_description` AS tproddesc INNER JOIN `" . DB_PREFIX . "product` AS tprod ON tproddesc.product_id = tprod.product_id WHERE tprod.sku = '". $this->db->escape($sku)."' AND tproddesc.language_id = '".(int) $language_id."'");

        return $query->row;
    }

    public function getProductIdFromSku($sku){
        $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE sku = '". $this->db->escape($sku)."'");

        return $query->row;
    }

    public function deleteDefaultCustomerAddress($customer_id){
        $this->db->query("DELETE FROM `" . DB_PREFIX . "address` WHERE customer_id = '" . (int)$customer_id . "'");
        $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET address_id = '0' WHERE customer_id = '" . (int)$customer_id . "'");
    }

    public function addCustomerToken($customer_id, $ip) {
        $token = token(32);

        $this->db->query("INSERT INTO `" . DB_PREFIX . "vsbridge_token` SET customer_id = '" . (int)$customer_id . "', token = '" . $this->db->escape($token) . "', ip = '" . $this->db->escape($ip) . "', timestamp = '".time()."'");

        return $token;
    }

    public function addCustomerRefreshToken($customer_id, $ip) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "vsbridge_refresh_token` SET customer_id = '" . (int)$customer_id . "', ip = '" . $this->db->escape($ip) . "', timestamp = '".time()."'");

        return $this->db->getLastId();
    }

    public function getCustomerRefreshToken($refresh_token_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "vsbridge_refresh_token` WHERE `vsbridge_refresh_token_id` = '" . (int) $refresh_token_id . "'");

        return $query->row;
    }

    public function getCustomerToken($token){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "vsbridge_token` WHERE `token` = '" . $this->db->escape($token) . "'");

        return $query->row;
    }

    public function getCustomerOrders($customer_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE customer_id ='". (int)$customer_id ."' ORDER BY order_id DESC");

        return $query->rows;
    }

    public function getOrderStatus($order_status_id, $language_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_status` WHERE order_status_id ='". (int)$order_status_id ."' AND language_id = '". (int)$language_id ."'");

        return $query->row;
    }

    public function getOrderProducts($order_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id ='". (int)$order_id ."'");

        return $query->rows;
    }

    public function getProduct($product_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product` WHERE product_id ='". (int)$product_id ."'");

        return $query->row;
    }

    public function getProductDetails($product_id, $language_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_description` AS tproddesc INNER JOIN `" . DB_PREFIX . "product` AS tprod ON tproddesc.product_id = tprod.product_id WHERE tprod.product_id = '". (int)$product_id ."' AND tproddesc.language_id = '".(int) $language_id."'");

        return $query->row;
    }

    public function getOrderTotals($order_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id ='". (int)$order_id ."'");

        return $query->rows;
    }

    public function getCustomerAddresses($customer_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "address` WHERE customer_id ='". (int)$customer_id ."'");

        return $query->rows;
    }

    public function getZone($zone_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id ='". (int)$zone_id ."'");

        return $query->row;
    }

    public function getCountry($country_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id ='". (int)$country_id ."'");

        return $query->row;
    }

    public function getCountryIdFromCode($country_code){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE iso_code_2 ='". $this->db->escape($country_code) ."'");

        if(isset($query->row['country_id'])){
            return $query->row['country_id'];
        }
    }

    public function getZoneIdFromName($zone_name){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE name LIKE '". $this->db->escape($zone_name) ."'");

        if(isset($query->row['zone_id'])){
            return $query->row['zone_id'];
        }
    }

    public function editCustomer($customer_id, $data) {
        if(!empty($data)){
            $update_array = array();

            if(isset($data['field']) && isset($data['value']) && isset($data['type'])){
                switch($data['type']){
                    default:
                    case 'string':
                        $data['value'] = $this->db->escape($data['value']);
                        break;
                    case 'integer':
                        $data['value'] = (int) $data['value'];
                        break;
                }

                array_push($update_array, $data['field']." = '".$data['value']."'");
            }

            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET ".implode(',', $update_array)." WHERE customer_id = '" . (int)$customer_id . "'");
        }
    }

    public function deleteCustomerAddresses($customer_id){
        $this->db->query("DELETE FROM `" . DB_PREFIX . "address` WHERE customer_id = '" . (int)$customer_id. "'");
    }

    public function addCustomerAddress($customer_id, $data, $default_address = false){
        $insert_array = array();

        foreach ($data as $datum) {

            if (isset($datum['field']) && isset($datum['value']) && isset($datum['type'])) {
                switch ($datum['type']) {
                    default:
                    case 'string':
                        $datum['value'] = $this->db->escape($datum['value']);
                        break;
                    case 'integer':
                        $datum['value'] = (int)$datum['value'];
                        break;
                }

                array_push($insert_array, $datum['field'] . " = '" . $datum['value'] . "'");
            }
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "address` SET customer_id = '". (int) $customer_id ."', ".implode(',', $insert_array));

        if(($inserted_id = $this->db->getLastId()) && ($default_address == true)){
            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET address_id = '". (int)$inserted_id ."' WHERE customer_id = '" . (int)$customer_id . "'");
        }
    }

    public function getCustomerSessionId($customer_id, $store_id){
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "vsbridge_session` WHERE customer_id = '". (int)$customer_id ."' AND store_id = '". (int)$store_id ."'");

        return $query->row;
    }

    public function SetCustomerSessionId($customer_id, $store_id, $session_id){
        $this->db->query("INSERT INTO `" . DB_PREFIX . "vsbridge_session` (`customer_id`, `store_id`, `session_id`) VALUES ('". (int)$customer_id ."', '". (int) $store_id ."', '". $this->db->escape($session_id) ."') ON DUPLICATE KEY UPDATE session_id = '" . $this->db->escape($session_id)  . "'");
    }

    public function transferCartProducts($source_session_id, $destination_session_id, $customer_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "cart` SET `customer_id` = '". (int)$customer_id ."', `session_id` = '". $this->db->escape($destination_session_id) ."' WHERE `session_id` = '". $this->db->escape($source_session_id) ."' AND `customer_id` = '0'");
    }

    public function getCustomerCartSessionId($customer_id){
        $query = $this->db->query("SELECT DISTINCT session_id FROM `" . DB_PREFIX . "cart` WHERE customer_id = '". (int) $customer_id ."' ORDER BY cart_id DESC LIMIT 1");

        return $query->row;
    }

    public function getCart($cart_id, $customer_id = null){
        $customer_lookup = "";

        if(isset($customer_id)){
            $customer_lookup = "AND customer_id = '". (int)$customer_id ."'";
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cart` WHERE session_id ='". $this->db->escape($cart_id) ."' ".$customer_lookup);

        return $query->rows;
    }

    public function getWeightClass($weight_class_id, $language_id) {
        $sql = "SELECT * FROM " . DB_PREFIX . "weight_class wc LEFT JOIN " . DB_PREFIX . "weight_class_description wcd ON (wc.weight_class_id = wcd.weight_class_id AND wcd.language_id = '". (int) $language_id ."') WHERE wc.weight_class_id = '". (int) $weight_class_id ."' ORDER BY title";
        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getLengthClass($length_class_id, $language_id) {
        $sql = "SELECT * FROM " . DB_PREFIX . "length_class lc LEFT JOIN " . DB_PREFIX . "length_class_description lcd ON (lc.length_class_id = lcd.length_class_id AND lcd.language_id = '". (int) $language_id ."') WHERe lc.length_class_id = '". (int) $length_class_id ."' ORDER BY title";

        $query = $this->db->query($sql);

        return $query->rows;
    }
}
