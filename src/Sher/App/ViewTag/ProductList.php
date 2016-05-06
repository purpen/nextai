<?php
/**
 * 产品列表标签
 * @author purpen
 */
class Sher_App_ViewTag_ProductList extends Doggy_Dt_Tag {
    
	protected $argstring;
    public function __construct($argstring, $parser, $pos = 0) {
        $this->argstring = $argstring;
    }
	
    /**
     * 列表的条件保持与索引顺序一致(non-PHPdoc)
     * @see Doggy/Dt/Doggy_Dt_Node#render()
     */
    public function render($context, $stream) {
        
		$page = 1;
        $size = 10;
		
		// 获取单个产品
		$product_id = 0;
		// 多个产品
		$product_ids = array();
		$sku = 0;
        $s_type = 0;
        $s_mark = 0;
        
		$category_id = 0;
        $user_id = 0;
		$published = 0;
		
		$sort = 'ordby';
        
        $var = 'list';
        $include_pager = 0;
        $pager_var = 'pager';
		
        $ttl = 900;
        $endmid = null;
		
        extract($this->resolve_args($context,$this->argstring,EXTR_IF_EXISTS));
		
        $page = (int) $page;
        $page || $page = 1;
        $size = (int)$size;
		
        $query = array();
        
		// 获取单个产品
		if ($product_id) {
			$result = DoggyX_Model_Mapper::load_model((int)$product_id, 'Sher_Core_Model_Product');
			$context->set($var, $result);
			return;
		}
		
		// 获取一组产品列表
		if (!empty($product_ids)){
			$result = DoggyX_Model_Mapper::load_model_list($product_ids, 'Sher_Core_Model_Product');
			$context->set($var, $result);
			return;
		}
		
		// 获取单个产品
		if ($sku) {
            $product = new Sher_Core_Model_Product();
            $result = $product->find_by_sku($sku);
			
			$context->set($var, $result);
			return;
		}
        
        if ($user_id) {
            $query['user_id'] = (int)$user_id;
        }
        
		if (!empty($category_id)) {
            if (is_array($category_id)){
                $query['category_id'] = array('$in'=>$category_id);
            }else{
                $query['category_id'] = (int)$category_id;
            }
		}
		
		if ($published) {
			$query['published'] = (int)$published;
		}
        
        // 搜索
        if ($s_mark && $s_type) {
            $words = Sher_Core_Service_Search::instance()->check_query_string($s_mark);
            switch ($s_type) {
                case 1:
                    $query['_id'] = (int)$s_mark;
                    break;
                case 2:
                    $query['title'] = array('$regex'=>new MongoRegex("/^$s_mark/i"));
                    break;
                case 3:
                    $query['tags'] = array('$all'=>$words);
                    break;
            }
        }
		
        $service = Sher_Core_Service_Product::instance();
        $options['page'] = $page;
        $options['size'] = $size;
		$options['sort_field'] = $sort;
		
        $result = $service->get_product_list($query, $options);
        $context->set($var,$result);
        if ($include_pager) {
            $context->set($pager_var,$result['pager']);
        }
    }
}
