<?php
/**
 * 附件 Model
 * @author purpen
 */
class Sher_Core_Model_Asset extends Sher_Core_Model_Base {

    protected $collection = "asset";
	
	private $file = null;
	private $file_content = null;

    const STATE_FAIL = 0;		//处理失败
    const STATE_PENDING = 1;
    const STATE_OK = 2;
    
    //类型
    const KIND_IMG = 1;
    const KIND_FILE = 2;
	
	# 照片
    const TYPE_PHOTO = 1;
	
	# 普通附件，编辑器图片
	const TYPE_ASSET = 2;
	# 广告图片
	const TYPE_AD = 3;
	
    # 用户头像
    const TYPE_AVATAR = 4;
	
	# 产品图片
	const TYPE_PRODUCT = 10;
    const TYPE_GALLERY_PRODUCT = 12;
	const TYPE_EDITOR_PRODUCT = 15;
    const TYPE_FILE_PRODUCT = 16;
    
	# 话题图片
	const TYPE_TOPIC   = 50;
	# 话题编辑器图片
	const TYPE_EDITOR_TOPIC = 55;

    protected $schema = array(
		'user_id' => '',
    	'parent_id' => '',
		
		## 原图信息
		'file_id' => '',
    	'filepath' => '',
		'filename' => '',
        'size' => 0,
        'width' => 0,
        'height' => 0,
		'mime' => null,
    	'desc'  =>  null,
		
		## 缩略图组
		/*
	    'mini'=> array(
			'filepath' => '',
			'width' => '',
			'height' => '',
			'type' => '',
		)*/
		'thumbnails' => array(
			'mini'    => array(),
			'medium'  => array(),
			'large'   => array(),
            'big'     => array(),
		),

        // 类型
        'kind' => 1,
		
		'domain' => Sher_Core_Util_Constant::STROAGE_ASSET,
		'asset_type' => self::TYPE_PRODUCT,
		'state' => 1,
    );
	
	# 附件类型
	protected $thumbnails = array('mini', 'medium', 'large', 'big');
	
    protected $required_fields = array('filepath');
	
    protected $int_fields = array('user_id', 'parent_id','size','width','height','asset_type','state');
	
    protected function extra_extend_model_row(&$row) {
        $row['id'] = (string)$row['_id'];
		if (!empty($row['filepath'])){
			$row['fileurl'] = $row['view_url'] = Sher_Core_Helper_Url::asset_view_url($row['filepath']);
		}
		
		$this->extend_asset_view_url($row);
    }
	
	/**
	 * 重建asset url
	 */
	protected function extend_asset_view_url(&$row){
		if (isset($row['thumbnails']) && ($row['kind'] == 1) && is_array($row['thumbnails'])) {
			foreach($row['thumbnails'] as $key => $value){
                Doggy_Log_Helper::debug("File Path: ".$row['thumbnails'][$key]['filepath']);
				$row['thumbnails'][$key]['view_url'] = Sher_Core_Helper_Url::asset_view_url($row['thumbnails'][$key]['filepath']);
			}
		} else {
			// 设置默认值
			$row['thumbnails'] = array(
				'mini'    => array(
					'view_url' => Doggy_Config::$vars['app.url.default_thumb_small'],
				),
				'medium'  => array(
					'view_url' => Doggy_Config::$vars['app.url.default_thumb_middle'],
				),
				'large'   => array(
					'view_url' => Doggy_Config::$vars['app.url.default_thumb_large'],
				),
				'big'     => array(
					'view_url' => Doggy_Config::$vars['app.url.default_thumb_large'],
				),
			);
		}
	}
	
	/**
	 * 更新附件类型
	 */
	public function update_thumbnails($thumb=array(),$type='tiny',$id=null) {
		if(in_array($type, $this->thumbnails)){
			# 过滤字段
			$new_data = array(
				'filepath' => $thumb['filepath'],
				'width' => floor($thumb['width']),
				'height' => floor($thumb['height']),
			);
			
			$this->update_set($id, array("thumbnails.${type}" => $new_data));
		}
	}
	
	/**
	 * 
	 */
    protected function before_save(&$data) {
		
    }
	
	/**
	 * 生成记录后，写文件进磁盘
	 */
	protected function after_save() {
		$file = $this->file();
		$file_content = $this->file_content();
        $kind = (int)$this->data['kind'];
		
	    $path = $this->filepath;
		$asset_id = (string)$this->data['_id'];
        
		Doggy_Log_Helper::debug("Path: $path, File: $file.");
		
		if(!is_null($path)){
			try{
				if (!is_null($file)) {
					Sher_Core_Util_Asset::storeAsset(Sher_Core_Util_Constant::ASSET_DOAMIN, $path, $file);
					// 云存储方式
					// Sher_Core_Util_Asset::store_asset_cloud($path, $file);
				}
				
				if (!is_null($file_content)) {
					Doggy_Log_Helper::debug("File content length: ".strlen($file_content));
					Sher_Core_Util_Asset::storeData(Sher_Core_Util_Constant::ASSET_DOAMIN, $path, $file_content);
					// Sher_Core_Util_Asset::store_data_cloud($path, $file_content);
				}
				
			}catch(Sher_Core_Util_Exception $e){
				Doggy_Log_Helper::error('Save asset file failed. ' . $e->getMessage());
				throw new Sher_Core_Model_Exception('Save asset file failed. ' . $e->getMessage());
			}
			
            if ($kind == self::KIND_IMG) {
    			// 生成其他缩略图
        		$thumbnails = Doggy_Config::$vars['app.asset.thumbnails'];
		
        		foreach($thumbnails as $key => $value){
                    Doggy_Log_Helper::warn("Maker image thumb $key / $value .");
            		$result = Sher_Core_Util_Image::maker_thumb($path, $value, Sher_Core_Util_Constant::STROAGE_ASSET, 1);
            		if (empty($result)){
            			Doggy_Log_Helper::warn("Maker image thumb Jobs failed: crop image result is null.");
            			return false;
            		}
		
            		$this->update_thumbnails($result, $key, $asset_id);
        		}
            }
            
		}
    }
	
	/**
	 * 批量更新附件所属对象
	 */
	public function update_batch_assets($ids=array(), $parent_id){
		for($i=0; $i<count($ids); $i++){
			$this->update_set($ids[$i], array('parent_id' => $parent_id));
		}
		return true;
	}
	
	/**
	 * 更新编辑器上传附件
	 */
	public function update_editor_asset($file_id, $parent_id){
		$criteria = array('file_id'=>$file_id);
		return $this->update_set($criteria, array('parent_id' => $parent_id), false, true, true);
	}
	
	/**
	 * 删除附件记录及附件文件
	 */
	public function remove_and_file($query=array()) {
		if(empty($query)){
			return false;
		}
		$rows = $this->find($query);
		foreach($rows as $row){
			$file_path = $row['filepath'];
			// 删除文件
			Sher_Core_Util_Asset::delete_cloud_file($file_path);
			
			$this->remove($row['_id']);
		}
		return true;
	}
	
	/**
	 * 删除附件记录
	 */
	public function delete_file($id) {
        $row = $this->find_by_id($id);
        if (empty($row)) {
            return null;
        }
        $file_path = $row['filepath'];
		
		// 删除文件
		Sher_Core_Util_Asset::delete_cloud_file($file_path);
		
        return $this->remove($id);
    }
	
	/**
	 * 通过path删除附件记录
	 */
	public function delete_by_path($file_path){
		if(empty($file_path)){
			return false;
		}
		
		// 截取首字符/
		if (strpos($file_path, '/') == 0){
			$file_path = substr($file_path, 1);
		}
		
		$row = $this->first(array(
			'filepath' => $file_path,
		));
		Doggy_Log_Helper::debug("Delete Path: $file_path");
		if(empty($row)){
			return false;
		}
		// 删除文件
		$res = Sher_Core_Util_Asset::delete_cloud_file($file_path);
		if (isset($res['error'])){
			return false;
		}
		
		return $this->remove($row['_id']);
	}
	
	/**
	 * 存储临时文件路径
	 */
	public function set_file($file){
		$this->file = $file;
	}
	
	public function file(){
		return $this->file;
	}
	
	/**
	 * 存储临时文件内容
	 */
	public function set_file_content($c){
		$this->file_content = $c;
	}
	
	public function file_content(){
		return $this->file_content;
	}

    /**
     * 返回所有缩略图后缀
     */
    public function thumb_info(){
        return $this->thumbnails_styles;
    }
}