<?php
/**
 * 产品列表标签
 * @author purpen
 */
class Sher_Core_Service_Product extends Sher_Core_Service_Base {
	
  protected $sort_fields = array(
	  'latest' => array('created_on' => -1),
      'update' => array('updated_on' => -1),
	  'hot' => array('love_count' => -1),
	  'price' => array('sale_price' => -1),
	  'sales' => array('sale_count' => -1),
	  'rand' => array('random' => 1),
	  'stick' => array('stick' => -1),
	  'comment' => array('comment_count' => -1),
	  'ordby' => array('order_by' => 1),
      
	);

    protected static $instance;
	
    /**
     * current service instance
     *
     * @return Sher_Core_Service_Product
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            return self::$instance = new Sher_Core_Service_Product();
        }
        return self::$instance;
    }

    /**
     * 获取列表
     */
    public function get_product_list($query=array(), $options=array()) {
	    $model = new Sher_Core_Model_Product();
		return $this->query_list($model, $query, $options);
    }
	
}