<?php
/**
 * 高级搜索
 * @author purpen
 */
class Sher_App_Action_Search extends Sher_App_Action_Base {
    
    public $stash = array(
		'page' => 1,
        'size' => 20,
		'q' => '',
		'ref' => '',
		't' => 0,
		'index_name' => 'full',
	);

	protected $exclude_method_list = array('execute');
	
    public function execute() {
       	$words = Sher_Core_Service_Search::instance()->check_query_string($this->stash['q']);
        return $this->_display_search_list($words);
	}
    	
	/**
	 * 搜索结果页
	 */
	protected function _display_search_list($words){
		$this->stash['has_scws'] = false;
        if(!empty($words)){
            $this->stash['has_scws'] = true;
            $query_string = $this->stash['q'];
            foreach ($words as $k=>$v){
                $query_string = str_replace($v,"<b class='ui magenta text'>{$v}</b>", $query_string);
            }
            $this->stash['highlight'] = $query_string;
        }
        // 搜索来源标记
        $search_ref = $this->stash['ref'];
        $visitor_id = $this->visitor->id;

        // key for cache search result
        $this->stash['search_result_key'] = md5($this->stash['q']).'::'.$this->stash['page'];
        
    	$this->stash['pager_url']  = sprintf(Doggy_Config::$vars['app.url.search'].'?q=%s&page=#p#', $this->stash['q']);
    	$this->stash['index_name'] = 'full';
        
		return $this->to_html_page('page/search.html');
	}
	
}