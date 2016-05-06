<?php
/**
 * 后台产品管理
 * @author purpen
 */
class Sher_Admin_Action_Product extends Sher_Admin_Action_Base {
	
	public $stash = array(
		'id' => 0,
		'page' => 1,
		'size' => 20,
		'published' => 0,
	);
	
	public function execute(){
		return $this->get_list();
	}
	
	/**
     * 产品列表
     * @return string
     */
    public function get_list() {
    	$this->set_target_css_state('page_product');
		$pager_url = Doggy_Config::$vars['app.url.admin'].'/product?published=%d&page=#p#';
		$this->stash['pager_url'] = sprintf($pager_url, $this->stash['published']);
    	$this->stash['is_search'] = false;
		
        return $this->to_html_page('admin/product/list.html');
    }
	
	/**
	 * 保存产品的销售信息
	 */
	public function save(){		
		$id = (int)$this->stash['_id'];
		
		// 分步骤保存信息
		$data = array();
		$data['title'] = $this->stash['title'];
		$data['advantage'] = $this->stash['advantage'];
		$data['summary'] = $this->stash['summary'];
		$data['content'] = $this->stash['content'];
		$data['category_id'] = $this->stash['category_id'];
		$data['tags'] = $this->stash['tags'];
        $data['order_by'] = $this->stash['order_by'];
        
		// 商品价格
		$data['market_price'] = $this->stash['market_price'];
		$data['sale_price'] = $this->stash['sale_price'];
		$data['inventory'] = $this->stash['inventory'];
        
        // 添加视频
        $data['video'] = array();
        if(isset($this->stash['video'])){
            foreach($this->stash['video'] as $v){
                if(!empty($v)){
                    array_push($data['video'], $v);
                }
            }
        }
		
		// 封面图
		$data['cover_id'] = $this->stash['cover_id'];
		// 检查是否有附件
		if(isset($this->stash['asset'])){
			$data['asset'] = $this->stash['asset'];
			$data['asset_count'] = count($data['asset']);
		}else{
			$data['asset'] = array();
			$data['asset_count'] = 0;
		}
		
		try{
			// 后台上传产品，默认通过审核
			$data['approved'] = 1;
			
			$model = new Sher_Core_Model_Product();
            
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

            $asset = new Sher_Core_Model_Asset();
			// 上传成功后，更新所属的附件
			if(isset($data['asset']) && !empty($data['asset'])){
				$asset->update_batch_assets($data['asset'], $id);
			}
            
            if (isset($this->stash['file_asset']) && !empty($this->stash['file_asset'])) {
                $asset->update_batch_assets($this->stash['file_asset'], $id);
            }
            
			// 保存成功后，更新编辑器图片
			if(!empty($this->stash['file_id'])){
			    Doggy_Log_Helper::debug("Upload file count for admin product");
			    $asset->update_editor_asset($this->stash['file_id'], (int)$id);
			}
            
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("Save product failed: ".$e->getMessage());
			return $this->ajax_json('保存失败:'.$e->getMessage(), true);
		}
		$redirect_url = Doggy_Config::$vars['app.url.admin'].'/product?page='.$this->stash['page'];
		
		return $this->ajax_json('保存成功.', false, $redirect_url);
	}
	
	/**
	 * 更新产品的其他sku及数量
	 * 已废弃！
	 */
	protected function update_product_inventory($modes, $product_id, $stage){
		try{
			
			foreach($modes as $mode){
				$inventory = new Sher_Core_Model_Inventory();
				$mode['product_id'] = (int)$product_id;
				$mode['stage'] = (int)$stage;
				if (empty($mode['r_id'])){
					$inventory->apply_and_save($mode);
				} else {
					// 补全_id
					$mode['_id'] = (int)$mode['r_id'];
					$inventory->apply_and_update($mode);
				}
				unset($inventory);
			}
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("Save product inventory failed: ".$e->getMessage());
		}
		return true;
	}
	
	/**
	 * 编辑或修改sku项
	 */
	public function edit_sku(){
		$product_id = (int)$this->stash['product_id'];
		$r_id = (int)$this->stash['r_id'];
		
		// 验证数据
		if(empty($product_id) || empty($r_id)){
			return $this->ajax_notification('编辑请求参数不足！', true);
		}
		$sku = array();
		
		$model = new Sher_Core_Model_Product();
		$product = $model->load($product_id);
		
		$inventory = new Sher_Core_Model_Inventory();
		$sku = $inventory->load((int)$r_id);
		
		$this->stash['sku'] = $sku;
		$this->stash['product'] = $product;
		
		return $this->to_taconite_page('ajax/sku_edit.html');
	}
	
	/**
	 * 新增或编辑产品的sku
	 */
	public function ajax_sku(){
		$product_id = (int)$this->stash['product_id'];
		$r_id = (int)$this->stash['r_id'];
		$mode = $this->stash['mode'];
		$price = $this->stash['price'];
		$quantity = (int)$this->stash['quantity'];
		
		// 验证数据
		if(empty($product_id) || empty($price) || empty($mode) || empty($quantity)){
			return $this->ajax_notification('设置SKU参数不足！', true);
		}
		
		$result = array();
		$action = 'create';
		
		try{
			$inventory = new Sher_Core_Model_Inventory();
			if (empty($r_id)){ // 新增
				$new_data = array(
					'product_id' => (int)$product_id,
					'mode' => $mode,
					'quantity' => (int)$quantity,
					'price' => (float)$price,
				);
				$ok = $inventory->apply_and_save($new_data);
				
				$r_id = $inventory->id;
			} else { // 更新
				$action = 'update';
				
				// 更新新数据
				$updated = array(
					'_id' => $r_id,
					'product_id' => (int)$product_id,
					'mode' => $mode,
					'quantity' => (int)$quantity,
					'price' => (float)$price,
				);
				$ok = $inventory->apply_and_update($updated);
			}
			$result = $inventory->load((int)$r_id);
			
			// 重新更新产品库存数量
			$total_quantity = $inventory->recount_product_inventory((int)$product_id, 1, false);
			
		}catch(Doggy_Model_ValidateException $e){
			return $this->ajax_notification('验证数据不能为空：'.$e->getMessage(), true);
		}catch(Sher_Core_Model_Exception $e){
			return $this->ajax_notification('操作失败,请重新再试', true);
		}
		
		$this->stash['sku'] = $result;
		$this->stash['action'] = $action;
		$this->stash['total_quantity']= $total_quantity;
		
		return $this->to_taconite_page('ajax/sku_item.html');
	}
	
	/**
	 * 删除产品的sku
	 */
	public function remove_sku(){
		$r_id = (int)$this->stash['r_id'];
		$product_id = (int)$this->stash['product_id'];
		if(empty($r_id)){
			return $this->ajax_notification('缺少sku参数.', true);
		}
		
		try{
			$result = array();
			
			$inventory = new Sher_Core_Model_Inventory();
			$ok = $inventory->remove($r_id);
			if($ok){
				$inventory->mock_after_remove($product_id);
			}
			$model = new Sher_Core_Model_Product();
			$product = $model->load($product_id);
		}catch(Sher_Core_Model_Exception $e){
			return $this->ajax_notification('操作失败,请重新再试', true);
		}
		
		$this->stash['id'] = $r_id;
		$this->stash['product'] = $product;
		
		return $this->to_taconite_page('ajax/del_sku.html');
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
			unset($model);
		}
	}
	
	/**
	 * 发布或编辑产品信息
	 */
	public function edit(){
		$id = (int)$this->stash['id'];
		$mode = 'create';
		
		$model = new Sher_Core_Model_Product();
		if(!empty($id)){
			$mode = 'edit';
			$product = $model->load($id);
	        if (!empty($product)) {
	            $product = $model->extended_model_row($product);
	        }
			$this->stash['product'] = $product;
		}
		$this->stash['mode'] = $mode;
		
        $this->stash['domain'] = Sher_Core_Util_Constant::STROAGE_PRODUCT;
        
		// 编辑器上传附件
		$callback_url = Doggy_Config::$vars['app.url.qiniu.onelink'];
		$this->stash['editor_pid'] = new MongoId();
		$this->stash['editor_asset_type'] = Sher_Core_Model_Asset::TYPE_EDITOR_PRODUCT;
		
		// 产品图片上传
		$this->stash['pid'] = new MongoId();
		$this->stash['asset_type'] = Sher_Core_Model_Asset::TYPE_PRODUCT;
        
        // 产品图集
        $this->stash['gid'] = new MongoId();
        $this->stash['gallery_type'] = Sher_Core_Model_Asset::TYPE_GALLERY_PRODUCT;
        
        
        $this->stash['file_type'] = Sher_Core_Model_Asset::TYPE_FILE_PRODUCT;
		
		return $this->to_html_page('admin/product/edit.html');
	}
	
	
	/**
	 * 更新发布上线
	 */
	public function update_onsale(){
		$id = $this->stash['id'];
		if(empty($id)){
			return $this->ajax_notification('缺少Id参数！', true);
		}
		
		$model = new Sher_Core_Model_Product();
		$ids = array_values(array_unique(preg_split('/[-]+/u',$id)));
		foreach($ids as $pid){
			$model->mark_as_published($pid);
		}
		
        $this->stash['ids']  = $ids;
		$this->stash['note'] = '发布上线成功！';
		return $this->to_taconite_page('ajax/published_ok.html');
	}
	
	/**
	 * 更新产品下架
	 */
	public function update_offline(){
		$ids = (int)$this->stash['id'];
		if(empty($ids)){
			return $this->ajax_notification('缺少Id参数！', true);
		}
		
		$model = new Sher_Core_Model_Product();
		$ids = array_values(array_unique(preg_split('/[-]+/u',$ids)));
		
		foreach($ids as $id){
			$model->mark_as_published($id, 1);
		}
		
        $this->stash['ids']  = $ids;
		$this->stash['note'] = '产品已下架成功！';
		
		return $this->to_taconite_page('ajax/published_ok.html');
	}
	
	
	/**
	 * 删除产品
	 */
	public function deleted(){
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
	
	/**
	 * 推荐
	 */
	public function ajax_stick(){
		$id = $this->stash['id'];
		if(empty($this->stash['id'])){
			return $this->ajax_notification('产品不存在！', true);
		}
		
		try{
			$model = new Sher_Core_Model_Product();
			$ok = $model->mark_as_stick((int)$id);
		}catch(Sher_Core_Model_Exception $e){
			return $this->ajax_notification('操作失败,请重新再试', true);
		}
		
		return $this->ajax_notification('操作成功');
	}
	
	/**
	 * 取消推荐
	 */
	public function ajax_cancel_stick(){
		$id = $this->stash['id'];
		if(empty($this->stash['id'])){
			return $this->ajax_notification('产品不存在！', true);
		}
		
		try{
			$model = new Sher_Core_Model_Product();
			$ok = $model->mark_cancel_stick((int)$id);			
		}catch(Sher_Core_Model_Exception $e){
			return $this->ajax_notification('操作失败,请重新再试', true);
		}
		
		return $this->ajax_notification('操作成功');
	}
	
	
	/**
	 * 订单产品评价
	 */
	public function evaluate(){
		$id = (int)$this->stash['id'];
		
		$model = new Sher_Core_Model_Product();
		if(!empty($id)){
			$product = $model->load($id);
	        if (!empty($product)) {
	            $product = $model->extended_model_row($product);
	        }
			$this->stash['product'] = $product;
		}
		
		return $this->to_html_page("admin/product/evaluate.html");
	}
	
	/**
	 * 用户发表评价
	 */
	public function ajax_evaluate(){
		$row = array();
		
		$row['user_id'] = $this->stash['user_id'];
		$row['star'] = $this->stash['star'];
		$row['target_id'] = $this->stash['target_id'];
		$row['content'] = $this->stash['content'];
		$row['type'] = (int)$this->stash['type'];
		
		// 验证数据
		if(empty($row['target_id']) || empty($row['content']) || empty($row['star'])){
			return $this->ajax_json('获取数据错误,请重新提交', true);
		}
		
		$model = new Sher_Core_Model_Comment();
		$ok = $model->apply_and_save($row);
		if($ok){
			$comment_id = $model->id;
			$this->stash['comment'] = &$model->extend_load($comment_id);
		}
		
        return $this->ajax_json('操作成功!', false);
		//return $this->to_taconite_page('ajax/evaluate_ok.html');
	}

    /**
     * 产品搜索
     */
    public function search(){
        $this->set_target_css_state('page_product');
        $this->stash['is_search'] = true;
		
		$pager_url = Doggy_Config::$vars['app.url.admin'].'/product/search?s=%d&q=%s&page=#p#';
		$this->stash['pager_url'] = sprintf($pager_url, $this->stash['s'], $this->stash['q']);
        return $this->to_html_page('admin/product/list.html');
  
    }

}

