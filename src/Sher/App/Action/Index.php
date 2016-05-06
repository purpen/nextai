<?php
/**
 * 首页,列表页面
 */
class Sher_App_Action_Index extends Sher_App_Action_Base {
    
	public $stash = array(
		'page'=>1,
		'q'=>'',
		'ref'=>'',
	);
	
	protected $exclude_method_list = array('execute', 'home', 'shouhuan');
	
	/**
	 * 网站入口
	 */
	public function execute(){		
		return $this->home();
	}
    
	/**
	 * 首页
	 */
	public function home(){
		return $this->to_html_page('page/home.html');
	}

	/**
	 * 手环
	 */
	public function shouhuan(){
		return $this->to_html_page('page/shouhuan.html');
	}
    
    
    
}
