<?php
/**
 * 全文检索标签
 * @author purpen
 */
class Sher_App_ViewTag_SearchList extends Doggy_Dt_Tag {
    protected $argstring;
    public function __construct($argstring, $parser, $pos = 0) {
        $this->argstring = $argstring;
    }

    public function render($context, $stream) {
        $page = 1;
        $size = 20;
        
        $search_word = '';
        $sort_field = 'latest';
		
        $index_name = 'full';
		
        $var = 'list';
        $include_pager = 0;
        $pager_var = 'pager';
		
        extract($this->resolve_args($context,$this->argstring,EXTR_IF_EXISTS));
		
        $page = (int)$page;
        $page = $page ? $page : 1;
        $size = (int)$size;
		
        $query = array();
        
        // 模糊查询
        if ($search_word) {
            $query['title'] = array('$regex'=>new MongoRegex("/^$search_word/i"));
        } else {
            $context->set($var, array());
            return;
        }
		
		$service = Sher_Core_Service_Product::instance();
		
        $options['page'] = $page;
        $options['size'] = $size;
		$options['sort_field'] = $sort_field;
        
        $result = $service->get_product_list($query, $options);
        $context->set($var, $result);
		
        if($include_pager){
            $context->set($pager_var, $result['pager']);
        }
    }
}