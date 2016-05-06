<?php
/**
 * 店铺管理
 * @author purpen
 */
class Sher_Admin_Action_Estore extends Sher_Admin_Action_Base implements DoggyX_Action_Initialize {
	
	public $stash = array(
		'id'   => 0,
		'page' => 1,
		'size' => 20,
        'lat'  => 0,
        'lng'  => 0,
		'approved' => 0,
	);
    
	public function _init() {
		$this->set_target_css_state('page_estore');
    }
	
	public function execute(){
		return $this->get_list();
	}
	
    /**
     * 课题搜索
     */
    public function search(){
        $this->stash['is_search'] = true;
		
		$pager_url = Doggy_Config::$vars['app.url.admin'].'/estore/search?s=%d&q=%s&page=#p#';
		$this->stash['pager_url'] = sprintf($pager_url, $this->stash['s'], $this->stash['q']);
        
        return $this->to_html_page('admin/estore/list.html');
    }
    
	/**
     * 店铺列表
     * @return string
     */
    public function get_list() {
    	
		$pager_url = Doggy_Config::$vars['app.url.admin'].'/estore?approved=%d&page=#p#';
		$this->stash['pager_url'] = sprintf($pager_url, $this->stash['approved']);
    	$this->stash['is_search'] = false;
		
        return $this->to_html_page('admin/estore/list.html');
    }
    
	/**
	 * 发布或编辑店铺信息
	 */
	public function edit(){
		$id = (int)$this->stash['id'];
		$mode = 'create';
		
		$model = new Sher_Core_Model_Estore();
		if(!empty($id)){
			$mode = 'edit';
			$estore = $model->load($id);
	        if (!empty($estore)) {
	            $estore = $model->extended_model_row($estore);
	        }
			$this->stash['estore'] = $estore;
		}
		$this->stash['mode'] = $mode;
        
        $areas = new Sher_Core_Model_Areas();
        $provinces = $areas->fetch_provinces();
        
        $this->stash['provinces'] = $provinces;
        
		return $this->to_html_page('admin/estore/edit.html');
	}
	
	/**
	 * 保存店铺信息
	 */
	public function save(){		
		$id = (int)$this->stash['_id'];
		
		// 分步骤保存信息
		$data = array();
		$data['name'] = $this->stash['name'];
		$data['phone'] = $this->stash['phone'];
        $data['mobile'] = $this->stash['mobile'];
        
        $data['address']  = $this->stash['address'];
        $data['worktime'] = $this->stash['worktime'];
        $data['fax'] = $this->stash['fax'];
        
        $data['city_id']  = (int)$this->stash['city_id'];
        
		try {
			$model = new Sher_Core_Model_Estore();
            
			if(empty($id)){
				$mode = 'create';
                
				$data['user_id'] = (int)$this->visitor->id;
				
				$ok = $model->apply_and_save($data);
				
				$id = (int)$model->id;
			}else{
				$mode = 'edit';
				$data['_id'] = $id;
				
				$ok = $model->apply_and_update($data);
			}
			
			if(!$ok){
				return $this->ajax_json('保存失败,请重新提交', true);
			}
            
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("Save product failed: ".$e->getMessage());
			return $this->ajax_json('保存失败:'.$e->getMessage(), true);
		}
		$redirect_url = Doggy_Config::$vars['app.url.admin'].'/estore?page='.$this->stash['page'];
		
		return $this->ajax_json('保存成功.', false, $redirect_url);
	}
    
    /**
     * 审核店铺
     */
    public function approved() {
		$id = $this->stash['id'];
        $approved = (int)$this->stash['approved'];
		if (empty($id)) {
			return $this->ajax_notification('店铺不存在！', true);
		}
        
		try {
            $ids = array_values(array_unique(preg_split('/[,，\s]+/u', $id)));
            
			$model = new Sher_Core_Model_Estore();
			foreach($ids as $id){
                if ($approved == Sher_Core_Model_Estore::APPROVED_OK) {
                    $model->mark_as_approved((int)$id);
                }
                if ($approved == Sher_Core_Model_Estore::APPROVED_NO) {
                    $model->mark_cancel_approved((int)$id);
                }
			}
			
			$this->stash['ids'] = $ids;
		} catch (Sher_Core_Model_Exception $e) {
			return $this->ajax_notification('操作失败,请重新再试', true);
		}
        
        $this->stash['note'] = '操作成功';
        
        return $this->to_taconite_page('ajax/published_ok.html');
    }
	
	/**
	 * 删除店铺
	 */
	public function deleted() {
		$id = $this->stash['id'];
		if(empty($id)){
			return $this->ajax_notification('产品不存在！', true);
		}
		
		$ids = array_values(array_unique(preg_split('/[,，\s]+/u', $id)));
		
		try{
			$model = new Sher_Core_Model_Product();
			
			foreach($ids as $id){
				$product = $model->load((int)$id);
				
				if (!empty($product)){
					$model->remove((int)$id);
				
					// 删除关联对象
					$model->mock_after_remove($id);
				}
			}
			
			$this->stash['ids'] = $ids;
			
		}catch(Sher_Core_Model_Exception $e){
			return $this->ajax_notification('操作失败,请重新再试', true);
		}
		
		return $this->to_taconite_page('ajax/delete.html');
	}

}