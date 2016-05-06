<?php
/**
 * 体验店列表
 * @author purpen
 */
class Sher_Core_Model_Estore extends Sher_Core_Model_Base {
    protected $collection = "estore";
    
    protected $mongo_id_style = DoggyX_Model_Mongo_Base::MONGO_ID_SEQ;
    
    protected $schema = array(
        'name'           => '',
        'phone'          => '',
        'mobile'         => '',
        'worktime'       => '',
        'address'        => '',
        'fax'            => '',
        
        'city_id'        => 0,
        
        # 合作方式，经销店、深度合作体验店
        'type'           => 0,
        
        'user_id'        => 0,
        
        # 计数
        'view_count'     => 0,
        
        # 推荐
        'sticked'        => 0,
        
        # 是否删除
        'deleted'        => 0,
        
        # 随机数
        'random'         => 0,
    );
    
    protected $required_fields = array('user_id','name');
    protected $int_fields = array('user_id','deleted');
    
    protected $joins = array(
        'province' => array('city_id' => 'Sher_Core_Model_Areas'),
    );
    
	/**
	 * 扩展数据
	 */
	protected function extra_extend_model_row(&$row) {        
        
	}
	
	/**
	 * 保存之前,处理标签中的逗号,空格等
	 */
	protected function before_save(&$data) {
		// 新建数据,补全默认值
		if ($this->is_saved()){
			// 添加随机数
			$data['random'] = Sher_Core_Helper_Util::gen_random();
		}
		
	    parent::before_save($data);
	}
    
    
}