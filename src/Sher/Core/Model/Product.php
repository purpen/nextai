<?php
/**
 * 产品Model
 * @author purpen
 */
class Sher_Core_Model_Product extends Sher_Core_Model_Base {

    protected $collection = "product";
    
	protected $mongo_id_style = DoggyX_Model_Mongo_Base::MONGO_ID_CUSTOM;
	
    protected $schema = array(
		'_id'     => null,
		# 产品名称
		'title'   => '',
		# 短标题
		'short_title' => '',
		# 优势/亮点
		'advantage' => '',
		# 简述
		'summary' => '',
		# 详情内容
        'content' => '',
		# 产品标签
		'tags'    => array(),
		# 排列顺序
		'order_by' => 0,
        # 参数内容
        'params' => '',
        
		# 产品视频链接
		'video' => array(),
		
		# 封面图
		'cover_id' => '',
		'asset' => array(),
		# 附件图片数
		'asset_count' => 0,
		
		# 类别支持多选
		'category_id' => array(),
		
		# 上传者
		'user_id' => null,
		
		## 价格
		
		# 成本价
		'cost_price'   => 0,
		# 市场价
		'market_price' => 0,
		# 销售价
		'sale_price'   => 0,
		
		# 库存数量
		'inventory'  => 0,
		# 销售数
		'sale_count' => 0,
		
		# 商品属性信息
		'attributes' => array(
			'width'  => 0,
			'height' => 0,
			'weight' => 0,
			'color'  => 0,
		),
		
		# 其他扩展信息
		'meta' => array(
			# 商品单位
			'unit' => null,
		),
		
		// 商店sku数量
		'sku_count' => 0,
		
		## 计数器
		
		# 浏览数
		'view_count'=>0,
		# 喜欢数
		'love_count' => 0,
		# 回应数 
		'comment_count' => 0,
		# 评价星数
		'comment_star' => 0,
		
		# 销售产品是否发布
		'published' => 1,
		
		# 推荐（编辑推荐、推荐至首页）
		'stick' => 0,
        # 精选
        'featured' => 0,
        
		# 随机数
		'random' => 0,
    );
	
	protected $required_fields = array('user_id','title');
	protected $int_fields = array('user_id','inventory','sale_count', 'published');
	protected $float_fields = array('cost_price', 'market_price', 'sale_price');
	protected $counter_fields = array('inventory','sale_count','asset_count', 'view_count', 'love_count', 'comment_count');
	protected $retrieve_fields = array('content'=>0);
	protected $joins = array(
	    'cover' => array('cover_id' => 'Sher_Core_Model_Asset'),
	);
	
	/**
	 * 扩展数据
	 */
	protected function extra_extend_model_row(&$row) {
        $row['view_url'] = Sher_Core_Helper_Url::shop_view_url($row['_id']);
        
		if(!isset($row['short_title']) || empty($row['short_title'])){
			$row['short_title'] = $row['title'];
		}
        
        $row['comment_view_url'] = sprintf(Doggy_Config::$vars['app.url.shop'].'/view/%d/%d', $row['_id'], 1);
		
		$row['tags_s'] = !empty($row['tags']) ? implode(',',$row['tags']) : '';
        $row['category_id_s'] = !empty($row['category_id']) ? implode(',', $row['category_id']) : '';
		
		// HTML 实体转换为字符
		if (isset($row['content'])){
			$row['content'] = htmlspecialchars_decode($row['content']);
		}
        
		// 去除 html/php标签
		$row['strip_summary'] = strip_tags(htmlspecialchars_decode($row['summary']));
		
		// 检测是否可售
		$row['can_saled'] = $this->can_saled($row);
        
	}
	
	/**
	 * 验证是否能够销售
	 */
	public function can_saled($data){
        if(isset($data['inventory'])){
			return $data['inventory'] > 0;
		}
		return false;
	}
	
	// 添加自定义ID
    protected function before_insert(&$data) {
        $data['_id'] = $this->gen_product_sku();
		Doggy_Log_Helper::warn("Create new product ".$data['_id']);
		
		parent::before_insert($data);
    }
	
	/**
	 * 保存之前,处理标签中的逗号,空格等
	 */
	protected function before_save(&$data) {
	    if (isset($data['tags']) && !is_array($data['tags'])) {
	        $data['tags'] = array_values(array_unique(preg_split('/[,，\s]+/u',$data['tags'])));
	    }
        // 转换数组
	    if (isset($data['category_id']) && is_array($data['category_id'])) {
            for($i=0;$i<count($data['category_id']);$i++) {
                $data['category_id'][$i] = (int)$data['category_id'][$i];
            }
	    }
        
        // 库存数量不为能负数
        if(isset($data['inventory']) && (int)$data['inventory'] < 0){
            $data['inventory'] = 0;
        }
		
		// 新建数据,补全默认值
		if ($this->is_saved()){
			// 添加随机数
			$data['random'] = Sher_Core_Helper_Util::gen_random();
		}
		
	    parent::before_save($data);
	}
	
    /**
	 * 保存之后事件
	 */
    protected function after_save(){
        // 如果是新的记录
        if($this->insert_mode){
            $category_id = $this->data['category_id'];
            if(!empty($category_id)){
                $category = new Sher_Core_Model_Category();
                $category->inc_counter('total_count', 1, $category_id);
                unset($category);
            }
        }
    }
	
	/**
	 * 通过sku查找
	 */
	public function find_by_sku($sku){
		$row = $this->first(array('sku'=>(int)$sku));
        if (!empty($row)) {
            $row = $this->extended_model_row($row);
        }
		
		return $row;
	}
	
	/**
	 * 分割分数
	 */
	protected function explode_point($value, $index=0){
		$point = explode('.', $value);
		if ($index == 1 && count($point) == 1){
			return '00';
		}
		return $point[$index];
	}
    
	/**
	 * 设置封面图
	 */
	public function mark_set_cover($id, $cover_id){
		return $this->update_set($id, array('cover_id'=>$cover_id));
	}
	
    /**
     * 标记为推荐
     */
    public function mark_as_stick($id) {
        return $this->update_set((int)$id, array('stick' => 1));
    }
	
    /**
     * 取消推荐
     */
	public function mark_cancel_stick($id) {
		return $this->update_set((int)$id, array('stick' => 0));
	}

    /**
     * 标记为精选
     */
    public function mark_as_featured($id) {
        return $this->update_set((int)$id, array('featured' => 1));
    }
	
    /**
     * 取消精选
     */
	public function mark_cancel_featured($id) {
		return $this->update_set((int)$id, array('featured' => 0));
	}
	
	/**
	 * 更新产品发布上线
	 */
    public function mark_as_published($id, $published=2) {
        return $this->update_set((int)$id, array('published' => $published));
	}
	
	/**
	 * 减少产品库存，及增加已销售数量
	 */
	public function decrease_invertory($id, $quantity=1, $only=false, $add_money=0, $add_people=1){
		$row = $this->find_by_id((int)$id);
		
		if (empty($row)){
			throw new Sher_Core_Model_Exception('产品不存在或已被删除！');
		}
		// 仅1个sku
		if ($only){
			$add_money = $row['sale_price']*$quantity;
		}
		
		// 减少库存数量
		$updated = array(
			'$inc' => array('sale_count'=>$quantity, 'inventory'=>$quantity*-1),
		);
        
        return $this->update((int)$id, $updated);
	}
	
	/**
	 * 恢复产品数量
	 */
	public function recover_invertory($id, $quantity=1, $only=false, $dec_money=0){
		$row = $this->find_by_id((int)$id);
		
		if (empty($row)){
			throw new Sher_Core_Model_Exception('产品不存在或已被删除！');
		}
		
		// 仅1个sku
		if ($only){
			$dec_money = $row['sale_price']*$quantity;
		}
        
		// 恢复库存数量
		$updated = array(
			'$inc' => array('sale_count'=>$quantity*-1, 'inventory'=>$quantity),
		);
		
		return $this->update((int)$id, $updated);
	}
	
	/**
	 * 更新最后的评价,并且comment_count+1
	 */
	public function update_last_reply($id, $user_id, $star){
		$query = array('_id'=> (int)$id);
		$new_data = array(
			'$inc' => array('comment_count'=>1, 'comment_star'=>(int)$star),
		);
		
		return self::$_db->update($this->collection,$query,$new_data,false,false,true);
	}
	
	/**
	 * 生成产品的SKU, SKU十位数字符
	 */
	protected function gen_product_sku($prefix='1'){
		$name = Doggy_Config::$vars['app.serialno.name'];
		
		$sku  = $prefix;
		$val = $this->next_seq_id($name);
		
		$len = strlen((string)$val);
		if ($len <= 5) {
			$sku .= date('md');
			$sku .= sprintf("%05d", $val);
		}else{
			$sku .= substr(date('md'), 0, 9 - $len);
			$sku .= $val; 
		}
		
		Doggy_Log_Helper::debug("Gen to product [$sku]");
		
		return (int)$sku;
	}
	
	/**
	 * 产生一个特定长度的数字字符串
	 */
	protected function rand_number_str($len=2, $chars='0123456789'){
        $string = '';
        for($i=0; $i<$len; $i++){
            $pos = rand(0, strlen($chars)-1);
            $string .= $chars{$pos};
        }
        return $string;
	}
	
	/**
	 * 增加计数
	 */
	public function inc_counter($field_name, $inc=1, $id=null){
		if(is_null($id)){
			$id = $this->id;
		}
		if(empty($id) || !in_array($field_name, $this->counter_fields)){
			return false;
		}
		
		return $this->inc($id, $field_name, $inc);
	}
	
	/**
	 * 减少计数
	 * 需验证，防止出现负数
	 */
	public function dec_counter($field_name,$id=null,$force=false,$count=1){
	    if(is_null($id)){
	        $id = $this->id;
	    }
	    if(empty($id)){
	        return false;
	    }
		if(!$force){
			$product = $this->find_by_id((int)$id);
			if(!isset($product[$field_name]) || $product[$field_name] <= 0){
				return true;
			}
		}
		
		return $this->dec($id, $field_name, $count);
	}
	
	/**
	 * 删除某附件
	 */
	public function delete_asset($id, $asset_id){
		// 从附件数组中删除
		$criteria = $this->_build_query($id);
		self::$_db->pull($this->collection, $criteria, 'asset', $asset_id);
		
		$this->dec_counter('asset_count', $id);
		
		// 删除Asset
		$asset = new Sher_Core_Model_Asset();
		$asset->delete_file($asset_id);
		unset($asset);
	}
	
	/**
	 * 删除后事件
	 */
	public function mock_after_remove($id) {
		// 删除Asset
		$asset = new Sher_Core_Model_Asset();
		$asset->remove_and_file(array('parent_id' => $id));
		unset($asset);
		
		// 删除Comment
		$comment = new Sher_Core_Model_Comment();
		$comment->remove(array('target_id' => $id));
		unset($asset);
		
		// 删除TextIndex
		$textindex = new Sher_Core_Model_TextIndex();
		$textindex->remove(array('target_id' => $id));
		unset($textindex);
		
		return true;
	}
	
}
