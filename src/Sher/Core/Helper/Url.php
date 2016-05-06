<?php
/**
 * 构造访问地址
 */
class Sher_Core_Helper_Url {
	
	/**
	 * 云存储 附件URL
	 */
	public static function asset_qiniu_view_url($key,$style=null){
		$asset_url = Doggy_Config::$vars['app.url.qiniu.frbird'].'/'.$key;
		if (!is_null($style)){
			$asset_url .= '-'.$style;
		}
		return $asset_url;
	}
	
	/**
	 * 附件的URL
	 */
	public static function asset_view_url($path, $domain='sher'){
		$asset_url = Sher_Core_Util_Asset::getAssetUrl($domain, $path);
		return $asset_url;
	}
	
	/**
	 * 类别的URL
	 */
	public static function category_view_url($id, $gid){
		return sprintf(Doggy_Config::$vars['app.url.category.list'], $id);
	}
    
	/**
	 * 用户头像
	 */
	public static function avatar_cloud_view_url($key, $style=null){
		$avatar_url = Doggy_Config::$vars['app.url.qiniu.frbird'].'/'.$key;
		if (!is_null($style)){
			$avatar_url .= '-'.$style;
		}
		return $avatar_url;
	}
	
	/**
	 * 用户默认头像
	 */
	public static function avatar_default_url($user_id, $type='m'){
		$avatar_default = Doggy_Config::$vars['app.url.packaged'].'/images/deavatar/';
        $avatar_file = '00'.substr((string)$user_id, -1);
		switch ($type) {
		    case 'b':
		        $avatar_default .= $avatar_file.'.jpg';
		        break;
		    case 'm':
		        $avatar_default .= $avatar_file.'-m.jpg';
		        break;
		    case 's':
		        $avatar_default .= $avatar_file.'-s.jpg';
		        break;
		}
        
		return $avatar_default;
	}
	
	/**
	 * 帖子列表访问地址
	 */
    public static function topic_list_url($category_id=null, $type=null, $time=null, $sort=null, $page=null) {
        if (!is_null($category_id)) {
            $category_id = 'c'.$category_id;
        }
		
        if (!empty($page)) {
            $page = "p${page}.html";
        }
		
        return self::build_url_path('app.url.topic', $category_id, $type, $time, $sort).$page;
    }
	
	/**
	 * 帖子列表访问地址,优化URL格式
	 */
	public static function topic_advance_list_url($category_id, $type, $time, $sort,$page=1) {
		return  sprintf(Doggy_Config::$vars['app.url.topic.list'], $category_id, $type, $time, $sort, $page);
	}
	
	/**
	 * 帖子查看地址
	 */
    public static function topic_view_url($topic_id,$page=1){
    	return sprintf(Doggy_Config::$vars['app.url.topic.view'], $topic_id, $page);
    }
	
	/**
	 * 产品销售查看地址
	 */
    public static function shop_list_url($category_id=null, $type=null, $sort=null, $page=null) {
        if (!is_null($category_id)) {
            $category_id = 'c'.$category_id;
        }
		
        if (!empty($page)) {
            $page = "p${page}.html";
        }
		
        return self::build_url_path('app.url.shop', $category_id, $type, $sort).$page;
    }
	
	/**
	 * 产品销售查看地址
	 */
    public static function shop_view_url($id, $page=1){
    	return sprintf(Doggy_Config::$vars['app.url.shop.view'], $id, $page);
    }
    
	/**
	 * 订单详情查看地址
	 */
    public static function order_view_url($rid){
    	return  sprintf(Doggy_Config::$vars['app.url.my.order_view'], $rid);
    }
	
	/**
	 * wap订单详情查看地址
	 */
    public static function order_mm_view_url($rid){
    	return  sprintf(Doggy_Config::$vars['app.url.my.order_mm_view'], $rid);
    }
    
	/**
	 * 跟踪推荐位查看地址
	 */
    public static function ad_view_url($id){
    	return  sprintf(Doggy_Config::$vars['app.url.advertise.view'], $id);
    }
	
    /**
     * 相关的话题列表地址
     */
    public static function user_topic_list_url($user_id, $page=null, $t=null) {
        if(!empty($page)){
            $page = "p${page}.html";
        }
        if(!empty($t)){
            $t = "t${t}";
        }
        
		return self::build_url_path('app.url.user', $user_id, 'topics', $t).$page;
    }
    
    /**
     * 他人喜欢的列表地址
     */
    public static function user_like_list_url($user_id, $page=null) {
        if (!empty($page)) {
            $page = "p${page}.html";
        }
		return self::build_url_path('app.url.user',$user_id,'like').$page;
    }
	
    /**
     * 他人发起的列表地址
     */
    public static function user_submitted_list_url($user_id, $page=null) {
        if (!empty($page)) {
            $page = "p${page}.html";
        }
		return self::build_url_path('app.url.user',$user_id,'submitted').$page;
    }
	
	/**
	 * 管理申请列表
	 */
    public static function admin_reply_list_url($state,$page=null) {
        if (!empty($page)) {
            $page = "?page=${page}";
        }
        return self::build_url_path('app.url.admin.reply','state',$state).$page;
    }

	/**
	 * 管理举报列表
	 */
    public static function admin_report_list_url($state,$page=null) {
        if (!empty($page)) {
            $page = "?page=${page}";
        }
        return self::build_url_path('app.url.admin.report','state',$state).$page;
    }
    
	/**
	 * 产品分类访问地址
	 */
    public static function stuff_list_url($category_id=null, $page=null) {
        if (!is_null($category_id)) {
            $category_id = 'c'.$category_id;
        }
        if (!empty($page)) {
            $page = "p${page}.html";
        }
        return self::build_url_path('app.url.stuff', $category_id).$page;
    }
	
    /**
     * 产品分享浏览地址
     */
    public static function stuff_view_url($stuff_id, $page=1){
    	return  sprintf(Doggy_Config::$vars['app.url.stuff.view'], $stuff_id, $page);
    }
	
	/**
	 * 产品灵感评论链接
	 */
	public static function stuff_comment_url($stuff_id, $page='#p#'){
		return  sprintf(Doggy_Config::$vars['app.url.stuff.comment'], $stuff_id, $page);
	}

    /**
     * 举报分享地址
     */
    public  static function stuff_report_url($stuff_id){
    	return  sprintf(Doggy_Config::$vars['app.url.stuff.report'],$stuff_id);
    }

    /**
     * 根据 $id 显示缩略图
     */
    public static function thumb_view_url($id){
        return sprintf(Doggy_Config::$vars['app.url.thumb'], $id);
    }

  	/**
     * 将参数作为url的path添加到指定的config key定义的url,生成一个友好的伪静态地址.
     *
     * @param string $url_config_key 指定的config key用于url前缀
     * @param string $force_trailing_slash
     * @return void
     */
    public static function build_url_path() {
        $args = func_get_args();
        if (empty($args)) {
            return '';
        }
        $key = array_shift($args);
        $url = Doggy_Config::$vars[$key];
        $url = rtrim($url,'/');
        while ($path = array_shift($args)) {
            $url .= '/'.$path;
        }
		
        return substr($url,-1,1) != '/' ? $url.'/':$url;
    }
	
    public static function user_home_url($id){
       return self::build_url_path('app.url.user',$id);
    }
	
    public static function asset_url($id) {
        return sprintf(Doggy_Config::$vars['app.url.asset_view'],$id);
    }
	
	public static function asset_ori_url($file_id) {
        return sprintf(Doggy_Config::$vars['app.url.asset_ori'],$file_id);
    }
    
	/**
	 * 设置向导
	 */
	public static function get_guide_url(){
		return self::build_url_path('app.url.guide');
	}
}