<?php
/**
 * 购买及支付流程
 * @author purpen
 */
class Sher_App_Action_Shopping extends Sher_App_Action_Base implements DoggyX_Action_Initialize {
	
	public $stash = array(
		'sku' => 0,
		'id' => 0,
		'rrid' => 0,
		'n'=>1, // 数量
		's' => 1, // 型号
		'page' => 1,
		'payaway' => '', // 支付机构
	);
	
	protected $page_tab = 'page_sns';
	protected $page_html = 'page/shopping/index.html';
	
	protected $exclude_method_list = array();
	
	public function _init() {
		$this->set_target_css_state('page_shop');
    }
	
	/**
	 * 社区
	 */
	public function execute(){
		return $this->cart();
	}
	
	/**
	 * 完整购物车页面
	 */
	public function cart() {
		$cart = new Sher_Core_Util_Cart();
		
        $products = $cart->getItems();
        $total_money = $cart->getTotalAmount();
        $items_count = $cart->getItemCount();
		
		if ($items_count > 0){
			$this->set_target_css_state('basket');
		}
		
		$this->stash['basket_products'] = $products;
		$this->stash['products'] = $products;
		$this->stash['total_money'] = $total_money;
		$this->stash['items_count'] = $items_count;
		
		return $this->to_html_page('page/shopping/cart.html');
	}
	
	/**
	 * 立即购买
	 */
	public function now_buy(){
		$sku = $this->stash['sku'];
		$quantity = $this->stash['n'];

		// 验证数据
		if (empty($sku) || empty($quantity)){
			return $this->show_message_page('操作异常，请重试！');
		}
		
		$user_id = $this->visitor->id;
		
		// 验证库存数量
		$inventory = new Sher_Core_Model_Inventory();
		$enoughed = $inventory->verify_enough_quantity($sku, $quantity);
		if(!$enoughed){
			return $this->show_message_page('挑选的产品已售完！', true);
		}
		$item = $inventory->load((int)$sku);
		
		$product_id = !empty($item) ? $item['product_id'] : $sku;
		
		// 获取产品信息
		$product = new Sher_Core_Model_Product();
		$product_data = $product->extend_load((int)$product_id);
		if(empty($product_data)){
			return $this->show_message_page('挑选的产品不存在或被删除，请核对！', true);
        }

        $this->stash['item_stage'] = 'shop';
        
        // 销售价格
        $price = !empty($item) ? $item['price'] : $product_data['sale_price'];

		$items = array(
			array(
				'sku'  => $sku,
				'product_id' => $product_id,
				'quantity' => $quantity,
				'price' => $price,
				'sale_price' => $price,
				'title' => $product_data['title'],
				'cover' => $product_data['cover']['thumbnails']['mini']['view_url'],
				'view_url' => $product_data['view_url'],
				'subtotal' => $price*$quantity,
			),
		);
		$total_money = $price*$quantity;
		$items_count = 1;
		
		$order_info = $this->create_temp_order($items, $total_money, $items_count);
		
		if (empty($order_info)){
			return $this->show_message_page('系统出了小差，请稍后重试！', true);
		}
		
		// 立即订单标识
		$this->stash['nowbuy'] = 1;
		
		// 获取快递费用
		$freight = Sher_Core_Util_Shopping::getFees();
		
		// 优惠活动费用
		$coin_money = 0.0;
		
		$pay_money = $total_money + $freight - $coin_money;
		
		$this->stash['order_info'] = $order_info;
		$this->stash['data'] = $order_info['dict'];
		$this->stash['pay_money'] = $pay_money;
		
		// 获取省市列表
		$areas = new Sher_Core_Model_Areas();
		$provinces = $areas->fetch_provinces();
		
		$this->stash['provinces'] = $provinces;
		
		$this->set_extra_params();
		
		return $this->to_html_page('page/shopping/checkout.html');
	}
	
	/**
	 * 加入购物车
	 */
	public function buy(){
		$sku = $this->stash['sku'];
		$quantity = $this->stash['n'];
		
		// 验证数据
		if (empty($sku) || empty($quantity)){
			return $this->ajax_note('请挑选需购买的产品！', true);
		}
		
		// 抢购商品不能加入购物车
		$inventory = new Sher_Core_Model_Inventory();
		$enoughed = $inventory->verify_enough_quantity($sku, $quantity);
		if(!$enoughed){
			return $this->ajax_note('挑选的产品已售完！', true);
		}
		$item = $inventory->load((int)$sku);
		
		$product_id = !empty($item) ? $item['product_id'] : $sku;

		// 获取产品信息
		$product = new Sher_Core_Model_Product();
		$product_data = $product->extend_load((int)$product_id);
		if(empty($product_data)){
			return $this->ajax_note('挑选的产品不存在或被删除，请核对！', true);
        }
		
		Doggy_Log_Helper::warn("Add to cart [$sku][$quantity]");
		
		$cart = new Sher_Core_Util_Cart();
		$cart->addItem($sku);
		$cart->setItemQuantity($sku, $quantity);
		
        //重置到cookie
        $cart->set();
		
		$total_money = $cart->getTotalAmount();
		$items_count = $cart->getItemCount();
		$products = $cart->getItems();
		
		$this->stash['basket_products'] = $products;
		$this->stash['total_money'] = $total_money;
		$this->stash['items_count'] = $items_count;
		
		$this->stash['action'] = 'add';
		
		return $this->to_taconite_page('ajax/cart_ok.html');
	}
	
	/**
	 * 增加购物车产品数量
	 */
	public function inc_qty(){
		$com_sku = $this->stash['sku'];
		$quantity = $this->stash['n'];
		$com_size = $this->stash['s'];
		
		// 验证数据
		if (empty($com_sku) || empty($quantity)){
			
		}
		
		$cart = new Sher_Core_Util_Cart();
		$cart->setItemQuantity($com_sku, $quantity);
		// 重置cookie
		$cart->set();
		
		// 获取购物车信息
		$this->stash['product'] = $cart->findItem($com_sku);
		$this->stash['total_money'] = $cart->getTotalAmount();
		$this->stash['items_count'] = $cart->getItemCount();
		
		return $this->ajax_json('数量增加成功！', false, '', $this->stash);
	}
	
	/**
	 * 减少购物车产品数量
	 */
	public function dec_qty(){
		$com_sku = $this->stash['sku'];
		$quantity = (int)$this->stash['n'];
		$com_size = $this->stash['s'];
		
		// 验证数据
		if (empty($com_sku)){
			return $this->ajax_json('缺少请求参数，请重试！', true);
		}
		
		$cart = new Sher_Core_Util_Cart();
		
		// 若n=0,从购物车删除
		if ($quantity <= 0){
			$cart->delItem($com_sku);
		} else {
			$cart->setItemQuantity($com_sku, $quantity);
		}
		
		// 重置cookie
		$cart->set();
		
		if ($quantity > 0){
			// 获取产品信息
			$this->stash['product'] = $cart->findItem($com_sku);
		}
		
		// 获取购物车信息
		$this->stash['total_money'] = $cart->getTotalAmount();
		$this->stash['items_count'] = $cart->getItemCount();
		
		return $this->ajax_json('数量减少成功！', false, '', $this->stash);
	}
	
	/**
	 * 从购物车中删除产品
	 */
	public function remove(){
		$com_sku = $this->stash['sku'];
		$com_size = $this->stash['s'];
		
		$cart = new Sher_Core_Util_Cart();
		
		Doggy_Log_Helper::warn("Remove before from the cart [$com_sku]");
		
		$cart->delItem($com_sku);
		
		// 重置cookie
		$cart->set();
		
		// 获取购物车信息
		$this->stash['total_money'] = $cart->getTotalAmount();
		$this->stash['items_count'] = $cart->getItemCount();
		
		$this->stash['action'] = 'delete';
		
		return $this->to_taconite_page('ajax/cart_ok.html');
	}
	
	/**
	 * 清空购物车
	 */
	public function clear(){
		$cart = new Sher_Core_Util_Cart();
		$cart->emptyCart();
	}
	
	
	/**
	 * 生产临时订单
	 */
	protected function create_temp_order($items=array(),$total_money,$items_count){
		$data = array();
		$data['items'] = $items;
		$data['total_money'] = $total_money;
		$data['items_count'] = $items_count;
	
		// 检测是否已设置默认地址
		$addbook = $this->get_default_addbook($this->visitor->id);
		if (!empty($addbook)){
			$data['addbook_id'] = (string)$addbook['_id'];
		}
		
		// 获取快递费用
		$freight = Sher_Core_Util_Shopping::getFees();
		
		// 优惠活动费用
		$coin_money = 0.0;
		
		// 红包金额
		$card_money = 0.0;

        //礼品券金额
        $gift_money = 0.0;

        //鸟币数量
        $bird_coin_count = 0;
        //鸟币抵金额
        $bird_coin_money = 0.0;
		
		// 设置订单默认值
		$default_data = array(
	        'payment_method' => 'a',
	        'transfer' => 'a',
	        'transfer_time' => 'a',
	        'summary' => '',
	        'invoice_type' => 0,
			'freight' => $freight,
			'card_money' => $card_money,
			'coin_money' => $coin_money,
            'gift_money' => $gift_money,
            'bird_coin_money' => $bird_coin_money,
            'bird_coin_count' => $bird_coin_count,
	        'invoice_caty' => 'p',
	        'invoice_content' => 'd'
	    );
		
		$new_data = array();
		$new_data['dict'] = array_merge($default_data, $data);
		
		$new_data['user_id'] = $this->visitor->id;
		$new_data['expired'] = time() + Sher_Core_Util_Constant::EXPIRE_TIME;
		
		try{
			$order_info = array();
			// 预生成临时订单
			$model = new Sher_Core_Model_OrderTemp();
			$ok = $model->apply_and_save($new_data);
			if ($ok) {
				$order_info = $model->get_data();
			}
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("Create temp order failed: ".$e->getMessage());
			return false;
		}
		
		return $order_info;
	}
	
	/**
	 * 填写订单信息
	 */
	public function checkout(){
		
		$user_id = $this->visitor->id;
		
		//验证购物车，无购物不可以去结算
		$cart = new Sher_Core_Util_Cart();
		if (empty($cart->com_list)){
			return $this->show_message_page('操作不当，请查看购物帮助！', true);
		}
		
        $items = $cart->getItems();
        $total_money = $cart->getTotalAmount();
        $items_count = $cart->getItemCount();
		
		// 获取省市列表
		$areas = new Sher_Core_Model_Areas();
		$provinces = $areas->fetch_provinces();
		
		try{
			// 预生成临时订单
			$model = new Sher_Core_Model_OrderTemp();
		
			$data = array();
			$data['items'] = $items;
			$data['total_money'] = $total_money;
			$data['items_count'] = $items_count;
		
			// 检测是否已设置默认地址
			$addbook = $this->get_default_addbook($user_id);
			if (!empty($addbook)){
				$data['addbook_id'] = (string)$addbook['_id'];
			}
			
			// 获取快递费用
			$freight = Sher_Core_Util_Shopping::getFees();
			
			// 优惠活动费用
			$coin_money = 0.0;
			
			// 红包金额
			$card_money = 0.0;

            //礼品券金额
            $gift_money = 0.0;

            //鸟币数量
            $bird_coin_count = 0;
            //鸟币抵金额
            $bird_coin_money = 0.0;
			
			// 设置订单默认值
			$default_data = array(
		        'payment_method' => 'a',
		        'transfer' => 'a',
		        'transfer_time' => 'a',
		        'summary' => '',
		        'invoice_type' => 0,
				'freight' => $freight,
				'card_money' => $card_money,
                'coin_money' => $coin_money,
                'gift_money' => $gift_money,
                'bird_coin_count' => $bird_coin_count,
                'bird_coin_money' => $bird_coin_money,
		        'invoice_caty' => 1,
		        'invoice_content' => 'd'
		    );
			$new_data = array();
			$new_data['dict'] = array_merge($default_data, $data);
			
			$new_data['user_id'] = $user_id;
			$new_data['expired'] = time() + Sher_Core_Util_Constant::EXPIRE_TIME;
			
			$ok = $model->apply_and_save($new_data);
			if ($ok) {
				$order_info = $model->get_data();
				$this->stash['order_info'] = $order_info;
				$this->stash['data'] = $order_info['dict'];
			}
			
			$pay_money = $total_money + $freight - $coin_money - $card_money - $gift_money - $bird_coin_money;
			
			$this->stash['pay_money'] = $pay_money;
			
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("Create temp order failed: ".$e->getMessage());
		}
		
		$this->stash['provinces'] = $provinces;
		
		$this->set_extra_params();
		
		return $this->to_html_page('page/shopping/checkout.html');
	}
	
	/**
	 * 确认订单并提交
	 */
	public function confirm(){
		$rrid = (int)$this->stash['rrid'];
		if(empty($rrid)){
			// 没有临时订单编号，为非法操作
			return $this->ajax_json('操作不当，请查看购物帮助！', true);
		}
		if(empty($this->stash['addbook_id'])){
			return $this->ajax_json('请选择收货地址！', true);
		}

        //验证地址
        $add_book_model = new Sher_Core_Model_AddBooks();
        $add_book = $add_book_model->find_by_id($this->stash['addbook_id']);
        if(empty($add_book)){
    		return $this->ajax_json('地址不存在！', true);
        }

		Doggy_Log_Helper::debug("Submit Order [$rrid]！");
		
		// 是否立即购买订单
		$is_nowbuy = isset($this->stash['is_nowbuy']) ? (int)$this->stash['is_nowbuy'] : false;
        
		// 验证购物车，无购物不可以去结算
		$cart = new Sher_Core_Util_Cart();
		if (!$is_nowbuy && empty($cart->com_list)){
			return $this->ajax_json('订单产品缺失，请重试！', true);
		}
		
		// 订单用户
		$user_id = $this->visitor->id;
		
		// 加载临时订单
		$model = new Sher_Core_Model_OrderTemp();
		$result = $model->load($rrid);
		if(empty($result)){
			return 	$this->ajax_json('订单预处理失败，请重试！', true);
		}
		
		// 订单临时信息
		$order_info = $result['dict'];
		
		// 获取订单编号
		$order_info['rid'] = $result['rid'];
		
		// 获取购物金额
        $total_money = $order_info['total_money'];
		
		// 获取提交数据, 覆盖默认数据
		$order_info['payment_method'] = $this->stash['payment_method'];
		$order_info['transfer'] = $this->stash['transfer'];
		$order_info['transfer_time'] = $this->stash['transfer_time'];
		
		// 需要开具发票，验证开票信息
		if(isset($this->stash['invoice_type'])){
			$order_info['invoice_type'] = $this->stash['invoice_type'];
			if ($order_info['invoice_type'] == 1){
				$order_info['invoice_title'] = $this->stash['invoice_title'];
				$order_info['invoice_caty'] = $this->stash['invoice_caty'];
			}
		}
		
		// 获取快递费用
		$freight = Sher_Core_Util_Shopping::getFees();
		
		// 优惠活动金额
		$coin_money = $order_info['coin_money'];
		
		// 红包金额
		$card_money = $order_info['card_money'];

		// 礼品卡金额
		$gift_money = $order_info['gift_money'];

        //鸟币数量
        $bird_coin_count = $order_info['bird_coin_count'];
        //鸟币抵金额
        $bird_coin_money = $order_info['bird_coin_money'];

        //红包和礼品卡不能同时 使用
        if(!empty($card_money) && !empty($gift_money)){
			return 	$this->ajax_json('红包和礼品卡不能同时使用！', true);
        }
		
		try{
			$orders = new Sher_Core_Model_Orders();
			
			$order_info['user_id'] = (int)$user_id;
			
			$order_info['addbook_id'] = $this->stash['addbook_id'];
			
			// 订单备注
			if(isset($this->stash['summary'])){
				$order_info['summary'] = $this->stash['summary'];
			}
			
			// 商品金额
			$order_info['total_money'] = $total_money;
			// 应付金额
			$pay_money = $total_money + $freight - $coin_money - $card_money - $gift_money - $bird_coin_money;
			// 支付金额不能为负数
			if($pay_money < 0){
				$pay_money = 0.0;
			}
			$order_info['pay_money'] = $pay_money;
			
			// 设置订单状态
			$order_info['status'] = Sher_Core_Util_Constant::ORDER_WAIT_PAYMENT;

            //验证积分兑换
            if( is_array($order_info['items']) && count($order_info['items'])==1 && isset($order_info['items'][0]['is_exchanged']) && $order_info['items'][0]['is_exchanged']==1){
      
                $product_id = $order_info['items'][0]['product_id'];
                // 再次验证用户积分并冻结用户相应的积分数量
                $check_bird = Sher_Core_Util_Shopping::check_and_freeze_bird_coin($order_info['bird_coin_count'], $order_info['user_id'], $product_id);
                if(!$check_bird['stat']){
 				    return $this->ajax_json($check_bird['msg'], true);       
                }
        
            }

			$ok = $orders->apply_and_save($order_info);
			// 订单保存成功
			if (!$ok) {
				return 	$this->ajax_json('订单生成失败，请重试！', true);
			}
			
			$data = $orders->get_data();
			
			$rid = $data['rid'];
			
			Doggy_Log_Helper::debug("Save Order [ $rid ] is OK!");
			
			// 清空购物车
			$cart->clearCookie();
			
			// 删除临时订单数据
			$model->remove($rrid);
			
			// 发送下订单成功通知
			
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("confirm order failed: ".$e->getMessage());
			return $this->ajax_json('订单处理异常，请重试！', true);
    	}

	    $next_url = Doggy_Config::$vars['app.url.shopping'].'/success?rid='.$rid;
		
		return $this->ajax_json('下订单成功！', false, $next_url);
	}
	
	/**
	 * 使用红包抵扣
	 */
	public function ajax_bonus(){
		$rid = $this->stash['rid'];
		$code = $this->stash['code'];
		if (empty($rid) || empty($code)) {
			return $this->ajax_json('操作不当，请查看购物帮助！', true);
    }

		try{
			$data = array();
			$model = new Sher_Core_Model_OrderTemp();
      $result = $model->first(array('rid'=>$rid));
      if (empty($result)){
        return $this->ajax_json('找不到订单表！', true);
      }

      $items = $result['dict']['items'];
			if(count($items) != 1){
				return $this->ajax_json('该红包仅限单一产品！', true);
			}
      $product_id = $items[0]['product_id'];

      //验证红包是否有效
      $total_money = $result['dict']['total_money'];
      $card_money = Sher_Core_Util_Shopping::get_card_money($code, $total_money, $product_id);

			// 更新临时订单
			$ok = $model->use_bonus($rid, $code, $card_money);
			if($ok){
				
				$result = $model->first(array('rid'=>$rid));
				if (empty($result)){
					return $this->ajax_json('订单操作失败，请重试！', true);
				}
				$dict = $result['dict'];
				$pay_money = $dict['total_money'] + $dict['freight'] - $dict['coin_money'] - $dict['card_money'] - $dict['gift_money'] - $dict['bird_coin_money'];
				
				// 支付金额不能为负数
				if($pay_money < 0){
					$pay_money = 0.0;
				}
				$data['discount_money'] = ($dict['coin_money'] +  $dict['card_money'] + $dict['gift_money'] + $dict['bird_coin_money'])*-1;
				$data['pay_money'] = $pay_money;
			}
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("Bonus order failed: ".$e->getMessage());
			return $this->ajax_json($e->getMessage(), true);
		}
		
		return $this->ajax_json('红包成功使用', false, null, $data);
	}

	/**
	 * 使用礼品码
	 */
	public function ajax_gift(){
		$rid = $this->stash['rid'];
		$code = $this->stash['code'];
		if(empty($rid) || empty($code)){
			return $this->ajax_json('订单编号或礼品码为空！', true);
		}
		
		try{
			// 验证订单信息
			$model = new Sher_Core_Model_OrderTemp();
			$order_info = $model->find_by_rid($rid);
			if(empty($order_info)){
				return $this->ajax_json('订单不存在！', true);
			}
			$items = $order_info['dict']['items'];
			if(count($items) != 1){
				return $this->ajax_json('礼品码仅限单一产品！', true);
			}
			
			// 验证礼品码
			$gift_money = Sher_Core_Util_Shopping::get_gift_money($code, $items[0]['product_id']);
			
			$data = array();
			// 更新临时订单
			$ok = $model->use_gift($rid, $code, $gift_money);
			if($ok){
				$result = $model->first(array('rid'=>$rid));
				if (empty($result)){
					return $this->ajax_json('订单操作失败，请重试！', true);
				}
				$dict = $result['dict'];
				$pay_money = $dict['total_money'] + $dict['freight'] - $dict['coin_money'] - $dict['card_money'] - $dict['gift_money'] - $dict['bird_coin_money'];
				
				// 支付金额不能为负数
				if($pay_money < 0){
					$pay_money = 0.0;
				}
				$data['discount_money'] = ($dict['coin_money'] +  $dict['card_money'] + $dict['gift_money'] + $dict['bird_coin_money'])*-1;
				$data['pay_money'] = $pay_money;
			}
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("Gift order failed: ".$e->getMessage());
			return $this->ajax_json($e->getMessage(), true);
		}
		
		return $this->ajax_json('礼品码成功使用', false, null, $data);
	}

  /**
   * 验证用户鸟币
   */
  public function ajax_check_bird_coin(){
    $user_id = $this->visitor->id;
		$rid = $this->stash['rid'];
		$bird_coin = $this->stash['bird_coin'];
		if(empty($rid) || empty($bird_coin)){
			return $this->ajax_json('订单编号或鸟币为空！', true);
		}
		
		try{
			// 验证订单信息
			$model = new Sher_Core_Model_OrderTemp();
			$order_info = $model->find_by_rid($rid);
			if(empty($order_info)){
				return $this->ajax_json('订单不存在！', true);
			}
			$items = $order_info['dict']['items'];
			if(count($items) != 1){
				return $this->ajax_json('仅限单一产品使用！', true);
			}
			
			// 验证鸟币-返回抵消金额 
			$bird_coin_money = Sher_Core_Util_Shopping::check_bird_coin($bird_coin, $user_id, $items[0]['product_id']);
			
			$data = array();
			// 更新临时订单
			$ok = $model->use_bird_coin($rid, $bird_coin, $bird_coin_money);
			if($ok){
				$result = $model->first(array('rid'=>$rid));
				if (empty($result)){
					return $this->ajax_json('订单操作失败，请重试！', true);
				}
				$dict = $result['dict'];
				$pay_money = $dict['total_money'] + $dict['freight'] - $dict['coin_money'] - $dict['card_money'] - $dict['gift_money'] - $dict['bird_coin_money'];
				
				// 支付金额不能为负数
				if($pay_money < 0){
					$pay_money = 0.0;
				}
				$data['discount_money'] = ($dict['coin_money'] + $dict['card_money'] + $dict['gift_money'] + $dict['bird_coin_money'])*-1;
				$data['pay_money'] = $pay_money;
			}
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn("use bird-coin order failed: ".$e->getMessage());
			return $this->ajax_json($e->getMessage(), true);
		}
		
		return $this->ajax_json('鸟币使用成功!', false, null, $data);
  }
	
	/**
	 * 下单成功，选择支付方式，开始支付
	 */
	public function success(){
		$rid = $this->stash['rid'];
		if (empty($rid)) {
			return $this->show_message_page('操作不当，请查看购物帮助！');
		}
		
		$model = new Sher_Core_Model_Orders();
		$order_info = $model->find_by_rid($rid);
		
		// 成功提交订单后，发送提醒邮件<异步进程处理>
		
		$this->stash['card_payed'] = false;
		// 验证是否需要跳转支付		
		if ($order_info['pay_money'] == 0.0 && ($order_info['total_money']+$order_info['freight'] <= $order_info['card_money'] + $order_info['coin_money'] + $order_info['gift_money'] + $order_info['bird_coin_money'])){
			$trade_prefix = 'Coin';
			if($order_info['gift_money'] > 0){
				$trade_prefix = 'Gift';
			}
			if($order_info['card_money'] > 0){
				$trade_prefix = 'Card';
			}
			if($order_info['bird_coin_money'] > 0){
				$trade_prefix = 'Bird_coin';
			}
			// 自动处理支付
			if ($order_info['status'] == Sher_Core_Util_Constant::ORDER_WAIT_PAYMENT){
				$trade_no = $trade_prefix.rand();
				$model->update_order_payment_info((string)$order_info['_id'], $trade_no, Sher_Core_Util_Constant::ORDER_READY_GOODS);
			}
			$this->stash['card_payed'] = true;
		}
		
		$this->stash['order'] = $order_info;
		
		return $this->to_html_page('page/shopping/success.html');
	}
	
	/**
	 * 处理支付
	 */
	public function payed(){
		$rid = $this->stash['rid'];
		$payaway = $this->stash['payaway'];
		if (empty($rid)) {
			return $this->show_message_page('操作不当，请查看购物帮助！');
		}
		if (empty($payaway)){
			$next_url = Doggy_Config::$vars['app.url.shopping'].'/success?rid='.$rid;
			return $this->show_message_page('请至少选择一种支付方式！', $next_url, 2000);
		}
		
		$model = new Sher_Core_Model_Orders();
		$order_info = $model->find_by_rid($rid);
		
		// 挑选支付机构
		Doggy_Log_Helper::warn('Pay away:'.$payaway);
		
		$pay_url = '';
		switch($payaway){
			case 'alipay':
				$pay_url = Doggy_Config::$vars['app.url.alipay'].'?rid='.$rid;
				break;
			case 'quickpay':
				$pay_url = Doggy_Config::$vars['app.url.quickpay'].'?rid='.$rid;
				break;
			case 'tenpay':
				$pay_url = Doggy_Config::$vars['app.url.tenpay'].'?rid='.$rid;
				break;
			default:
				// 网上银行支付
				$pay_url = Doggy_Config::$vars['app.url.alipay'].'?rid='.$rid.'&bank='.$payaway;
				break;
		}
		
		return $this->to_redirect($pay_url);
	}
	
	/**
	 * 获取默认地址，无默认地址，取第一个地址
	 */
	protected function get_default_addbook($user_id){
		$addbooks = new Sher_Core_Model_AddBooks();
		
		$query = array(
			'user_id' => (int)$user_id,
			'is_default' => 1
		);
		$options = array(
			'sort' => array('created_on' => -1),
		);
		$result = $addbooks->first($query);
		
		return $result;
	}
	
    /**
     * 设置订单的扩展参数
     * @return void
     */
    protected function set_extra_params($province=null){
        $order = new Sher_Core_Model_Orders();
		
        //获取付款方式列表
        $payment_methods = $order->find_payment_methods();
		$this->stash['payment_methods'] = $payment_methods;
		
        //获取送货方式
        $transfer_methods = $order->find_transfer_methods();
		if(!empty($province)){
			$order->validate_express_fees($province);
			$transfer_methods['a']['freight'] = $order->getFees();
		}
		$this->stash['transfer_methods'] = $transfer_methods;
		
        //获取送货时间列表
        $transfer_times = $order->find_transfer_time();
		$this->stash['transfer_times'] = $transfer_times;
		
        //获取发票内容类型
        $invoice_category = $order->find_invoice_category();
		$this->stash['invoice_category'] = $invoice_category;
        
        unset($order);
    }
	
    /**
     * 修改配送地址
     */
	public function ajax_address(){
		$model = new Sher_Core_Model_AddBooks();
		
		$id = $this->stash['_id'];
		
		$data = array();
		
		$data['name'] = $this->stash['name'];
		$data['phone'] = $this->stash['phone'];
		$data['province'] = $this->stash['province'];
		$data['city']  = $this->stash['city'];
		$data['address'] = $this->stash['address'];
		$data['zip']  = $this->stash['zip'];
		
		try{
			if(empty($id)){
				$mode = 'create';
				
				$data['user_id'] = $this->visitor->id;
				
				$ok = $model->apply_and_save($data);
			}else{
				$mode = 'edit';
				
				$data['_id'] = $id;
				
				$ok = $model->apply_and_update($data);
			}
			
			if(!$ok){
				return $this->ajax_json('新地址保存失败,请重新提交', true);
			}
			
		}catch(Sher_Core_Model_Exception $e){
			Doggy_Log_Helper::warn('新地址保存失败:'.$e->getMessage());
			
			return $this->ajax_json('新地址保存失败:'.$e->getMessage(), true);
		}
		
		return $this->ajax_json('新地址保存成功！', false, '' );
	}
	
    /**
     * 修改支付方式
     */
	public function ajax_payment(){
		
	}
	
    /**
     * 修改订单备注信息
     */
	public function ajax_notice(){
		
	}
	
}

