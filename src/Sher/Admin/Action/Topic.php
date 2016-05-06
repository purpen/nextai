<?php
/**
 * 话题管理
 * @author tianshuai
 */
class Sher_Admin_Action_Topic extends Sher_Admin_Action_Base implements DoggyX_Action_Initialize {
	
	public $stash = array(
		'page' => 1,
		'size' => 20,
        'sort' => 0,
        'start_date' => '',
        'end_date' => '',
        'start_time' => 0,
        'end_time' => 0,
        's' => '',
        'q' => '',
	);
	
	public function _init() {
		$this->set_target_css_state('page_topic');
    }
	
	/**
	 * 入口
	 */
	public function execute() {
		return $this->get_list();
	}
	
	/**
	 * 列表--全部
	 */
	public function get_list() {
        $this->set_target_css_state('all_list');
		$page = (int)$this->stash['page'];

        $this->stash['start_time'] = isset($this->stash['start_time']) ? (int)$this->stash['start_time'] : 0;
        $this->stash['end_time'] = isset($this->stash['end_time']) ? (int)$this->stash['end_time'] : 0;

		$this->stash['category_id'] = 0;
		$this->stash['is_top'] = true;

        if($this->stash['start_date']){
            $this->stash['start_time'] = strtotime($this->stash['start_date']);
        }elseif(!empty($this->stash['start_time'])){
            $this->stash['start_date'] = date('Y-m-d', (int)$this->stash['start_time']);
        }

        if($this->stash['end_date']){
            $this->stash['end_time'] = strtotime($this->stash['end_date']);  
        }elseif(!empty($this->stash['end_time'])){
            $this->stash['end_date'] = date('Y-m-d', (int)$this->stash['end_time']);
        }
		
		$pager_url = Doggy_Config::$vars['app.url.admin'].'/topic/get_list?s=%d&q=%s&sort=%d&start_time=%d&end_time=%d&page=#p#';

		$this->stash['pager_url'] = sprintf($pager_url, $this->stash['s'], $this->stash['q'], $this->stash['sort'], $this->stash['start_time'], $this->stash['end_time']);
		
		return $this->to_html_page('admin/topic/list.html');
	}

	/**
	 * 创建/更新
	 */
	public function submit(){
		
		$id = isset($this->stash['id'])?(int)$this->stash['id']:0;
		$mode = 'create';
		$model = new Sher_Core_Model_Topic();

		if(!empty($id)){
			$mode = 'edit';
			$topic = $model->find_by_id($id);
			$topic = $model->extended_model_row($topic);
			$this->stash['topic'] = $topic;
		}
		$this->stash['mode'] = $mode;
        
		// 图片上传参数
		$this->stash['token'] = Sher_Core_Helper_Util::generate_mongo_id();
		$this->stash['domain'] = Sher_Core_Util_Constant::STROAGE_TOPIC;
		$this->stash['asset_type'] = Sher_Core_Model_Asset::TYPE_TOPIC;
		$new_file_id = new MongoId();
		$this->stash['new_file_id'] = (string)$new_file_id;
		$this->stash['pid'] = Sher_Core_Helper_Util::generate_mongo_id();
        
        $this->stash['groups'] = $model->find_groups();
        
		return $this->to_html_page('admin/topic/submit.html');
	}

	/**
	 * 保存
	 */
	public function save() {
		// 验证数据
		if(empty($this->stash['title'])){
			return $this->ajax_json('标题不能为空！', true);
		}
		
		$id = (int)$this->stash['_id'];
        
		$mode = 'create';
		$data = array();
        
		$data['_id'] = $id;
		$data['title'] = $this->stash['title'];
		$data['description'] = $this->stash['description'];
		
		//自动添加关键词内链
		$data['description'] = Sher_Core_Helper_Util::gen_inlink_keyword($data['description'], 1);
		$data['tags'] = $this->stash['tags'];
		$data['category_id'] = $this->stash['category_id'];
		
		$data['cover_id'] = $this->stash['cover_id'];
        
		$data['published'] = (int)$this->stash['published'];

		$data['source'] = isset($this->stash['source'])?$this->stash['source']:'';
		$data['attrbute'] = isset($this->stash['attrbute'])?(int)$this->stash['attrbute']:0;
		
		// 检测编辑器图片数
		$file_count = isset($this->stash['file_count']) ? (int)$this->stash['file_count'] : 0;
        $newadd_asset_ids = isset($this->stash['newadd_asset_ids'])?$this->stash['newadd_asset_ids']:'';
        
		// 检查是否有图片
		if(isset($this->stash['asset'])){
			$data['asset'] = $this->stash['asset'];
		}else{
			$data['asset'] = array();
		}

		// 检查是否有附件
		if(isset($this->stash['file_asset'])){
			$data['file_asset'] = $this->stash['file_asset'];
		}else{
			$data['file_asset'] = array();
		}
		
		try{
			$model = new Sher_Core_Model_Topic();
			// 新建记录
			if(empty($id)){
				$data['user_id'] = (int)$this->visitor->id;
				$ok = $model->apply_and_save($data);
				$topic = $model->get_data();
				$id = $topic['_id'];
				
				// 更新用户主题数量
				$this->visitor->inc_counter('topic_count', $data['user_id']);
				
			}else{
				$mode = 'edit';
				$ok = $model->apply_and_update($data);
			}
			
			if(!$ok){
				return $this->ajax_json('保存失败,请重新提交', true);
			}
			
			// 上传成功后，更新所属的图片
			if(isset($data['asset']) && !empty($data['asset'])){
				$this->update_batch_assets($data['asset'], $id);
			}
            // 上传成功后，更新编辑器图片
            if(!empty($newadd_asset_ids)){
                $newadd_asset = array_filter(array_unique(preg_split('/[,]+/u', $newadd_asset_ids)));
                $this->update_batch_assets($newadd_asset, $id);
            }
			
			// 保存成功后，更新编辑器图片
			Doggy_Log_Helper::debug("Upload file count[$file_count].");
			if(!empty($this->stash['file_id'])){
				$model->update_editor_asset($id, $this->stash['file_id']);
			}
				
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("创意保存失败：".$e->getMessage());
			return $this->ajax_json('创意保存失败:'.$e->getMessage(), true);
		}
		
		$redirect_url = Doggy_Config::$vars['app.url.admin'].'/topic';
		
		return $this->ajax_json('保存成功.', false, $redirect_url);
	}

	/**
	 * 批量更新附件所属
	 */
	protected function update_batch_assets($ids=array(), $parent_id){
		if (!empty($ids)){
			$model = new Sher_Core_Model_Asset();
			foreach($ids as $id){
				Doggy_Log_Helper::debug("Update asset[$id] parent_id: $parent_id");
				$model->update_set($id, array('parent_id' => $parent_id));
			}
		}
	}
    
	/**
	 * 删除
	 */
	public function deleted(){
		$id = isset($this->stash['id'])?$this->stash['id']:0;
		if(empty($id)){
			return $this->ajax_notification('删除对象不存在！', true);
		}
		
		$ids = array_values(array_unique(preg_split('/[,，\s]+/u', $id)));
		
		try{
			$model = new Sher_Core_Model_Topic();
			
			foreach($ids as $id){
				$topic = $model->load((int)$id);
				
        if (!empty($topic)){
		      $model->remove((int)$id);
			    $model->mock_after_remove($id, $topic);
				}
			}
			
			$this->stash['ids'] = $ids;
			
		}catch(Sher_Core_Model_Exception $e){
			return $this->ajax_notification('操作失败,请重新再试', true);
		}
		
		return $this->to_taconite_page('ajax/delete.html');
	}

    /**
     * 搜索
     */
    public function search(){
        $this->stash['is_search'] = true;
        $this->stash['start_time'] = $this->stash['end_time'] = 0;
        if($this->stash['start_date']){
            $this->stash['start_time'] = strtotime($this->stash['start_date']);
        }

        if($this->stash['end_date']){
            $this->stash['end_time'] = strtotime($this->stash['end_date']);  
        }
		
		$pager_url = Doggy_Config::$vars['app.url.admin'].'/topic/search?s=%d&q=%s&sort=%d&start_time=%d&end_time=%d&page=#p#';

		$this->stash['pager_url'] = sprintf($pager_url, $this->stash['s'], $this->stash['q'], $this->stash['sort'], $this->stash['start_time'], $this->stash['end_time']);
        
        return $this->to_html_page('admin/topic/list.html');
    }

    /**
     * 推荐／取消
     */
    public function ajax_stick(){
 		$ids = $this->stash['id'];
		$evt = isset($this->stash['evt'])?(int)$this->stash['evt']:0;
		if(empty($ids)){
			return $this->ajax_notification('缺少Id参数！', true);
		}
		
		$model = new Sher_Core_Model_Topic();
		$ids = array_values(array_unique(preg_split('/[,，\s]+/u',$ids)));
		
		foreach($ids as $id){
			$result = $model->mark_as_stick((int)$id, $evt);
		}
		
		$this->stash['note'] = '操作成功！';
		
		return $this->to_taconite_page('ajax/published_ok.html');
    }


}