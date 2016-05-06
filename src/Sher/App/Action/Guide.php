<?php
/**
 * 关于我们
 * @author purpen
 */
class Sher_App_Action_Guide extends Sher_App_Action_Base {
	public $stash = array(
	    'city_id' => 1,
	);
	
	protected $exclude_method_list = '*';
	
	/**
	 * 默认入口
	 */
	public function execute(){
		return $this->about();
	}
	
	/**
	 * 关于我们
	 */
	public function about() {
		$this->set_target_css_state('page_about');
		return $this->to_html_page('page/guide/about.html');
	}
    
	/**
	 * 公司介绍
	 */
	public function company() {
		$this->set_target_css_state('page_company');
		return $this->to_html_page('page/guide/company.html');
	}
    
	/**
	 * 品牌文化
	 */
	public function brand() {
		$this->set_target_css_state('page_brand');
		return $this->to_html_page('page/guide/brand.html');
	}
    
	/**
	 * 发展纪事
	 */
	public function history() {
		$this->set_target_css_state('page_history');
		return $this->to_html_page('page/guide/history.html');
	}
    
	/**
	 * 资质荣誉
	 */
	public function prize() {
		$this->set_target_css_state('page_prize');
		return $this->to_html_page('page/guide/prize.html');
	}
	
	/**
	 * 联系我们
	 */
	public function contact() {
		$this->set_target_css_state('page_contact');
		return $this->to_html_page('page/guide/contact.html');
	}
    
	/**
	 * 服务支持
	 */
	public function service() {
		$this->set_target_css_state('page_service');
		return $this->to_html_page('page/guide/service.html');
	}
    
	/**
	 * 售后服务
	 */
	public function finishsaled() {
		$this->set_target_css_state('page_finishsaled');
		return $this->to_html_page('page/guide/finishsaled.html');
	}
    
	/**
	 * 门店展示
	 */
	public function store() {
		$this->set_target_css_state('page_store');
		return $this->to_html_page('page/guide/store.html');
	}
    
	/**
	 * 销售网点
	 */
	public function outlets() {
		$this->set_target_css_state('page_outlets');
        
        $areas = new Sher_Core_Model_Areas();
        $provinces = $areas->fetch_provinces();
        
        $this->stash['provinces'] = $provinces;
        
		return $this->to_html_page('page/guide/outlets.html');
	}
    
	

}