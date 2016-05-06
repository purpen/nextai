<?php
/**
 * 新闻中心
 * @author purpen
 */
class Sher_App_Action_Topic extends Sher_App_Action_Base implements DoggyX_Action_Initialize {
	
	public $stash = array(
		'id' => '',
		'type' => 0,
		'cid' => 0,
		'sort' => 0,
		'page' => 1,
		'size' => 3,
	);
	
	protected $page_tab = 'page_sns';
	protected $page_html = 'page/topic/list.html';
	
	protected $exclude_method_list = array('execute');
	
	public function _init() {
		$this->set_target_css_state('page_topic');        
		$this->stash['domain'] = Sher_Core_Util_Constant::TYPE_TOPIC;
        
        // 获取登陆者信息
        $this->stash['user'] = array();
        if($this->visitor->id){
            $user = new Sher_Core_Model_User();
            $row = $user->load((int)$this->visitor->id);
            if(!empty($row)){
                $this->stash['user'] = $user->extended_model_row($row);
            }
        }
    }
	
	/**
	 * 社区
	 */
	public function execute(){
		return $this->index();
	}

	/**
	 * 社区首页
	 */
	public function index(){
		$cid = $this->stash['cid'];
		$type = $this->stash['type'];
	    $sort = $this->stash['sort'] = isset($this->stash['sort']) ? (int)$this->stash['sort'] : 7;
        $page = $this->stash['page'];
        
		$topic = new Sher_Core_Model_Topic();
        
        $this->stash['groups'] = $topic->find_groups();
        
		return $this->to_html_page('page/topic/index.html');
	}
    
	/**
	 * 显示主题详情帖
	 */
	public function view(){
		$id = (int)$this->stash['id'];
		
		$redirect_url = Doggy_Config::$vars['app.url.topic'];
		if(empty($id)){
			return $this->show_message_page('访问的新闻不存在！', $redirect_url);
		}
		// 是否允许编辑
		$editable = false;
		
		if(isset($this->stash['referer'])){
			$this->stash['referer'] = Sher_Core_Helper_Util::RemoveXSS($this->stash['referer']);
		}
		
		$model = new Sher_Core_Model_Topic();
		$topic = $model->load($id);
		
		if(empty($topic) || $topic['deleted']){
			return $this->show_message_page('访问的新闻不存在或已被删除！', $redirect_url);
		}
        if (!empty($topic)) {
            $topic = $model->extended_model_row($topic);
        }
        
		// 增加pv++
		$inc_ran = rand(1,6);
		$model->increase_counter('view_count', $inc_ran, $id);
        
		// 是否出现后一页按钮
	    if(isset($this->stash['referer'])){
            $this->stash['HTTP_REFERER'] = $this->current_page_ref();
	    }
		
        $this->stash['topic'] = $topic;
        
		return $this->to_html_page('page/topic/show.html');
	}
}
