<?php
/**
 * 店铺列表标签
 * @author purpen
 */
class Sher_Core_ViewTag_EstoreList extends Doggy_Dt_Tag {
    
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
        
        $city_id = 0;
        
		$sort = 'latest';
        
        $var = 'list';
        $include_pager = 0;
        $pager_var = 'pager';
		
        extract($this->resolve_args($context,$this->argstring,EXTR_IF_EXISTS));
		
        $page = (int) $page;
        $page || $page = 1;
        $size = (int)$size;
		
        $query = array();
        
		if ($city_id) {
			$query['city_id'] = (int)$city_id;
		}
		
        $service = Sher_Core_Service_Estore::instance();
        
        $options['page'] = $page;
        $options['size'] = $size;
		$options['sort_field'] = $sort;
        
        $result = $service->get_store_list($query, $options);
        $context->set($var,$result);
        
        if ($include_pager) {
            $context->set($pager_var,$result['pager']);
        }
    }
}