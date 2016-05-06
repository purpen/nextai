<?php
/**
 * 商店
 * @author purpen
 */
class Sher_App_Action_Shop extends Sher_App_Action_Base implements DoggyX_Action_Initialize {
	
	public $stash = array(
		'id' => '',
		'sku' => '',
		'type' => 0,
		'category_id' => 0,
		'sort' => 0,
		'topic_id' => '',
		'page' => 1,
		'size' => 3,
		'sword' => '',
	);
	
	protected $page_tab = 'page_sns';
	protected $page_html = 'page/shop/index.html';
	
	protected $exclude_method_list = array('execute','speaker','headset','wireless','hifi','getlist','view','nowbuy','gallery','specs');
	
	public function _init() {
		$this->set_target_css_state('page_shop');
		$this->stash['domain'] = Sher_Core_Util_Constant::TYPE_PRODUCT;
    }
	
	/**
	 * 社区
	 */
	public function execute(){
		return $this->speaker();
	}
	
    /**
     * 获取列表
     */
    public function getlist() {
		$pager_url = Doggy_Config::$vars['app.url.shop'].'/getlist?category_id=%d&page=#p#';
		$this->stash['pager_url'] = sprintf($pager_url, $this->stash['category_id']);
        return $this->_display_tab_list();
    }
    
    /**
     * 显示列表
     */
    protected function _display_tab_list() {
		$category_id = (int)$this->stash['category_id'];
		$page = (int)$this->stash['page'];
        
        $parent_category = array();
        
        $category = new Sher_Core_Model_Category();
        // 默认未选择某分类
        if(empty($category_id)){
            $parent = $category->find(array('pid'=>0, 'is_open'=>1));
            if(!empty($parent)){
                for($k=0;$k<count($parent);$k++){
                    $children = $category->find(array('pid'=>$parent[$k]['_id'],'is_open'=>1));
                    for($i=0;$i<count($children);$i++){
                        $query_category_id[] = $children[$i]['_id'];
                    }
                }
            } else {
                // 设置无穷大的数字，相当于结果集为空
                $query_category_id = 1000000;
            }
            $parent_id = 0;
            $current_category = array();
        } else {
    		// 获取当前类别信息
    		$current_category = $category->extend_load((int)$category_id);
            $istop = ($current_category['pid'] == 0) ? true : false;
            // 获取所有子分类下产品列表
            if ($istop) {
                $children = $category->find(array('pid'=>(int)$category_id,'is_open'=>1));
                for($i=0;$i<count($children);$i++){
                    $query_category_id[] = $children[$i]['_id'];
                }
                if (empty($children)){
                    $query_category_id = $category_id;
                }
                $parent_id = $category_id;
            } else {
                $query_category_id = $category_id;
                $parent_id = $current_category['pid'];
                
                // 获取父级
                $parent_category = $category->extend_load((int)$parent_id);
            }
        }
        
		$this->stash['current_category'] = $current_category;
        $this->stash['parent_category']  = $parent_category;
        $this->stash['parent_id'] = $parent_id;
        
        return $this->to_html_page('page/shop/tab_list.html');
    }
    
	/**
	 * 查看产品详情
	 */
	public function view() {
		$id = (int)$this->stash['id'];
		
		$redirect_url = Doggy_Config::$vars['app.url.shop'];
		if(empty($id)){
			return $this->show_message_page('访问的产品不存在！', $redirect_url);
		}
		
		if(isset($this->stash['referer'])){
			$this->stash['referer'] = Sher_Core_Helper_Util::RemoveXSS($this->stash['referer']);
		}
		
		$model = new Sher_Core_Model_Product();
		$product = $model->load((int)$id);
        if (!empty($product)) {
            $product = $model->extended_model_row($product);
        }
		
		if(empty($product)){
			return $this->show_message_page('访问的产品不存在或已被删除！', $redirect_url);
		}

		// 未发布上线的产品，仅允许本人及管理员查看
		if(!$product['published'] && !($this->visitor->can_admin() || $product['user_id'] == $this->visitor->id)){
			return $this->show_message_page('访问的产品等待发布中！', $redirect_url);
		}
        		
		// 增加pv++
		$model->inc_counter('view_count', 1, $id);
		
		$this->stash['product'] = $product;
		$this->stash['id'] = $id;
		
		return $this->to_html_page('page/shop/view.html');
	}
	
    /**
     * 立即购买
     */
    public function nowbuy() {
		$id = (int)$this->stash['id'];
		
		$redirect_url = Doggy_Config::$vars['app.url.shop'];
		if(empty($id)){
			return $this->show_message_page('访问的产品不存在！', $redirect_url);
		}
		
		if(isset($this->stash['referer'])){
			$this->stash['referer'] = Sher_Core_Helper_Util::RemoveXSS($this->stash['referer']);
		}
		
		$model = new Sher_Core_Model_Product();
		$product = $model->load((int)$id);
        if (!empty($product)) {
            $product = $model->extended_model_row($product);
        }
		
		if(empty($product)){
			return $this->show_message_page('访问的产品不存在或已被删除！', $redirect_url);
		}

		// 未发布上线的产品，仅允许本人及管理员查看
		if(!$product['published'] && !($this->visitor->can_admin() || $product['user_id'] == $this->visitor->id)){
			return $this->show_message_page('访问的产品等待发布中！', $redirect_url);
		}
        		
		// 增加pv++
		$model->inc_counter('view_count', 1, $id);
        
		// 验证是否还有库存
		$product['can_saled'] = $model->can_saled($product);
		
		// 获取skus及inventory
		$inventory = new Sher_Core_Model_Inventory();
		$skus = $inventory->find(array(
			'product_id' => $id
		));
		$this->stash['skus'] = $skus;
		$this->stash['skus_count'] = count($skus);

		//评论参数
		$comment_options = array(
            'comment_target_id' =>  $product['_id'],
            'comment_target_user_id' => $product['user_id'],
            'comment_type'  =>  4,
            'comment_pager' =>  Sher_Core_Helper_Url::shop_view_url($id, '#p#'),
            // 是否显示上传图片/链接
            'comment_show_rich' => 1,
		);
		$this->_comment_param($comment_options);
		
		$this->stash['product'] = $product;
		$this->stash['id'] = $id;
		
		return $this->to_html_page('page/shop/buypage.html');
    }
    
    /**
     * 产品图集
     */
    public function gallery() {
		$id = (int)$this->stash['id'];
		
		$redirect_url = Doggy_Config::$vars['app.url.shop'];
		if(empty($id)){
			return $this->show_message_page('访问的产品不存在！', $redirect_url);
		}
		
		if(isset($this->stash['referer'])){
			$this->stash['referer'] = Sher_Core_Helper_Util::RemoveXSS($this->stash['referer']);
		}
		
		$model = new Sher_Core_Model_Product();
		$product = $model->load((int)$id);
        if (!empty($product)) {
            $product = $model->extended_model_row($product);
        }
		
		if(empty($product)){
			return $this->show_message_page('访问的产品不存在或已被删除！', $redirect_url);
		}

		// 未发布上线的产品，仅允许本人及管理员查看
		if(!$product['published'] && !($this->visitor->can_admin() || $product['user_id'] == $this->visitor->id)){
			return $this->show_message_page('访问的产品等待发布中！', $redirect_url);
		}
        		
		// 增加pv++
		$model->inc_counter('view_count', 1, $id);
		
		$this->stash['product'] = $product;
		$this->stash['id'] = $id;
		
		return $this->to_html_page('page/shop/gallery.html');
    }
    
    /**
     * 参数
     */
    public function specs() {
		$id = (int)$this->stash['id'];
		
		$redirect_url = Doggy_Config::$vars['app.url.shop'];
		if(empty($id)){
			return $this->show_message_page('访问的产品不存在！', $redirect_url);
		}
		
		if(isset($this->stash['referer'])){
			$this->stash['referer'] = Sher_Core_Helper_Util::RemoveXSS($this->stash['referer']);
		}
		
		$model = new Sher_Core_Model_Product();
		$product = $model->load((int)$id);
        if (!empty($product)) {
            $product = $model->extended_model_row($product);
        }
		
		if(empty($product)){
			return $this->show_message_page('访问的产品不存在或已被删除！', $redirect_url);
		}

		// 未发布上线的产品，仅允许本人及管理员查看
		if(!$product['published'] && !($this->visitor->can_admin() || $product['user_id'] == $this->visitor->id)){
			return $this->show_message_page('访问的产品等待发布中！', $redirect_url);
		}
        		
		// 增加pv++
		$model->inc_counter('view_count', 1, $id);
		
		$this->stash['product'] = $product;
		$this->stash['id'] = $id;
		
		return $this->to_html_page('page/shop/specs.html');
    } 
    
	/**
	 * ajax获取产品用户评价
	 */
	public function ajax_fetch_comment(){
        $current_user_id = $this->visitor->id?(int)$this->visitor->id:0;
		$this->stash['page'] = isset($this->stash['page'])?(int)$this->stash['page']:1;
		$this->stash['per_page'] = isset($this->stash['per_page'])?(int)$this->stash['per_page']:8;
		$this->stash['total_page'] = isset($this->stash['total_page'])?(int)$this->stash['total_page']:1;
        $this->stash['current_user_id'] = $current_user_id;
        
		return $this->to_taconite_page('ajax/fetch_shop_comment.html');
	}
    
	/**
	 * 编辑快捷评价
	 */
	public function edit_ajax_evaluate(){

        if(!$this->visitor->can_edit){
     		  return $this->ajax_json('没有权限!', true);	   
        }
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
	}

    /**
     * ajax加载商品列表
     */
    public function ajax_load_list(){        
        $category_id = $this->stash['category_id'];
        $presaled = isset($this->stash['presaled'])?$this->stash['presaled']:0;
        $type = $this->stash['type'];
        
        $page = $this->stash['page'];
        $size = $this->stash['size'];
        $sort = $this->stash['sort'];
        
        $service = Sher_Core_Service_Product::instance();
        $query = array();
        $options = array();
        
		if ($category_id) {
			$query['category_id'] = (int)$category_id;
		}
        // is_shop=1
        $query['stage'] = array('$in'=>array(5, 9, 12, 15));
        
		// 预售
		if ($presaled) {
		  $query['stage'] = 5;
		}
        // 仅发布
        $query['published'] = 1;
        
        if($type){
            switch((int)$type){
                case 1:
                    $query['stage'] = 15;
                    break;
                case 2:
                    $query['stage'] = array('$in'=>array(5,9));
                    break;
                case 3:
                    $query['stage'] = 12;
                    break;
                case 4:
                    $query['stick'] = 1;
                    break;
                case 5:
                    $query['featured'] = 1;
                    break;
                case 6:
                    $query['snatched'] = 1;
                    break;
                case 7:
                    $query['stage'] = 5;
                    break;
            }
        }
		// 排序
		switch ((int)$sort) {
			case 0:
				$options['sort_field'] = 'latest';
				break;
			case 1:
				$options['sort_field'] = 'vote';
				break;
			case 2:
				$options['sort_field'] = 'love';
				break;
			case 3:
				$options['sort_field'] = 'comment';
				break;
			case 4:
				$options['sort_field'] = 'stick:update';
				break;
			case 5:
				$options['sort_field'] = 'featured:update';
				break;
		}
        
        $options['page'] = $page;
        $options['size'] = $size;

        //限制输出字段
        $some_fields = array(
          '_id'=>1, 'title'=>1, 'short_title'=>1, 'snatched'=>1, 'featured'=>1,
          'stage'=>1, 'stick'=>1, 'category_id'=>1, 'created_on'=>1, 'asset_count'=>1, 'vote_favor_count'=>1,
          'advantage'=>1, 'sale_price'=>1, 'cover_id'=>1, 'comment_count'=>1, 'view_count'=>1,
          'updated_on'=>1, 'favorite_count'=>1, 'love_count'=>1, 'deleted'=>1,'presale_money'=>1, 'tags'=>1,
          'vote_oppose_count'=>1, 'summary'=>1, 'voted_finish_time'=>1, 'succeed'=>1, 'presale_finish_time'=>1,
          'sale_count'=>1,
        );
        $options['some_fields'] = $some_fields;
        
        $result = $service->get_product_list($query, $options);

        $max = count($result['rows']);
        for($i=0;$i<$max;$i++){
          // 过滤用户表
          if(isset($result['rows'][$i]['user'])){
            $result['rows'][$i]['user'] = Sher_Core_Helper_FilterFields::user_list($result['rows'][$i]['user']);
          }
          if(isset($result['rows'][$i]['designer'])){
            $result['rows'][$i]['designer'] = Sher_Core_Helper_FilterFields::user_list($result['rows'][$i]['designer']);
          }
        }

        $data = array();
        $data['results'] = $result;
        
        return $this->ajax_json('', false, '', $data);
    }
    
    /**
     * 评论参数
     */
    protected function _comment_param($options){
        $this->stash['comment_target_id'] = $options['comment_target_id'];
        $this->stash['comment_target_user_id'] = $options['comment_target_user_id'];
        $this->stash['comment_type'] = $options['comment_type'];
        // 评论的链接URL
        $this->stash['pager_url'] = isset($options['comment_pager'])?$options['comment_pager']:0;

        // 是否显示图文并茂
        $this->stash['comment_show_rich'] = isset($options['comment_show_rich'])?$options['comment_show_rich']:0;
  }
	
}
