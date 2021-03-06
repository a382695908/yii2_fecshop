<?php
/**
 * FecShop file.
 *
 * @link http://www.fecshop.com/
 * @copyright Copyright (c) 2016 FecShop Software LLC
 * @license http://www.fecshop.com/license/
 */
namespace fecshop\services\cart;
use Yii;
use yii\base\InvalidValueException;
use yii\base\InvalidConfigException;
use fecshop\services\Service;
use fecshop\models\mysqldb\Cart as MyCart;
/**
 * Cart services
 * @author Terry Zhao <2358269014@qq.com>
 * @since 1.0
 */
class Quote extends Service
{
	const SESSION_CART_ID = 'current_session_cart_id';
	protected $_cart_id;
	protected $_cart;
	protected $_shipping_cost;
	/**
	 * 存储购物车的信息。
	 */
	protected $cartInfo;

	/**
	 * @return Int  得到cart_id
	 * Cart的session的超时时间由session组件决定。
	 */
	public function getCartId(){
		if(!$this->_cart_id){
			$cart_id = Yii::$app->session->get(self::SESSION_CART_ID);
			$this->_cart_id = $cart_id;	
		}
		return $this->_cart_id;
	}
	/**
	 * @property $address|Array 地址信息数组
	 * @property $shipping_method | String 货运方式
	 * @property $payment_method | String 支付方式
	 * @property boolean
	 * 更新游客购物车信息，用户下次下单 或者 重新下单，可以不需要重新填写货运地址信息。
	 */
	public function updateGuestCart($address,$shipping_method,$payment_method){
		$cart = $this->getCurrentCart();
		if($cart){
			$cart->customer_firstname 		= $address['first_name'];
			$cart->customer_lastname 		= $address['last_name'];
			$cart->customer_email 			= $address['email'];
			$cart->customer_telephone 		= $address['telephone'];
			$cart->customer_address_street1 = $address['street1'];
			$cart->customer_address_street2 = $address['street2'];
			$cart->customer_address_country = $address['country'];
			$cart->customer_address_city 	= $address['city'];
			$cart->customer_address_state 	= $address['state'];
			$cart->customer_address_zip 	= $address['zip'];
			
			$cart->shipping_method 	= $shipping_method;
			$cart->payment_method 	= $payment_method;
			return $cart->save();
		}
	}
	
	/**
	 * @property $address_id | int 用户customer address id
	 * @property $shipping_method 货运方式
	 * @property $payment_method  支付方式
	 * @property boolean
	 * 登录用户的cart信息，进行更新，更新cart的$address_id,$shipping_method,$payment_method。
	 * 用途：对于登录用户，create new address（在下单页面），新创建的address会被保存，
	 * 然后需要把address_id更新到cart中。
	 * 对于 shipping_method 和 payment_method，保存到cart中，下次进入下单页面，会被记录
	 * 下次登录用户进行下单，进入下单页面，会自动填写。
	 */
	public function updateLoginCart($address_id,$shipping_method,$payment_method){
		$cart = $this->getCurrentCart();
		if($cart && $address_id){
			$cart->customer_address_id 		= $address_id;
			$cart->shipping_method 	= $shipping_method;
			$cart->payment_method 	= $payment_method;
			return $cart->save();
		}
	}
	/**
	 * @return Object
	 * 得到当前的cart，如果当前的cart不存在，
	 * 则返回为空（注意，这个就是 getCurrentCart() 和 getCart()两个函数的区别）
	 */
	public function getCurrentCart(){
		if(!$this->_cart){
			$cart_id = $this->getCartId();
			if($cart_id){
				$one = MyCart::findOne(['cart_id' => $cart_id]);
				if($one['cart_id']){
					$this->_cart = $one;
				}
			}
		}
		return $this->_cart;
	}
	/**
	 * 如果当前的Cart不存在，则创建Cart
	 * 如果当前的cart存在，则查询，如果查询得到cart，则返回，如果查询不到，则重新创建
	 * 设置$this->_cart 为 上面新建或者查询得到的cart对象。
	 */
	public function getCart(){
		if(!$this->_cart){
			$cart_id = $this->getCartId();
			if(!$cart_id){
				$this->createCart();
			}else{
				$one = MyCart::findOne(['cart_id' => $cart_id]);
				if($one['cart_id']){
					$this->_cart = $one;
				}else{
					# 如果上面查询为空，则创建cart
					$this->createCart();
				}
			}
		}
		return $this->_cart;
	}
	/**
	 * @property $cart | MyCart Object
	 * 设置$this->_cart 为 当前传递的$cart对象。
	 */
	public function setCart($cart){
		$this->_cart = $cart;
	}
	/**
	 * 得到购物车中产品的个数。头部的ajax请求一般访问这个
	 */
	public function getCartItemCount(){
		$items_count = 0;
		if($cart_id = $this->getCartId()){
			$one = MyCart::findOne(['cart_id' => $cart_id]);
			if($one['items_count']){
				$items_count = $one['items_count'];
			}
		}
		return $items_count;
	}
	/**
	 * @property $item_qty | Int ，当$item_qty 不等于null时，代表
	 * 		已经知道购物车中产品的个数，不需要去cart_item表中查询。
	 *		譬如清空购物车操作，直接就知道产品个数肯定为零。
	 * 当购物车的产品变动后，更新cart表的产品总数
	 */
	public function computeCartInfo($item_qty = null){
		if($item_qty === null){
			$item_qty = Yii::$service->cart->quoteItem->getItemQty();
		}
		$cart = $this->getCart();
		$cart->items_count = $item_qty;
		$cart->save();
		return true;
	}
	/**
	 * 得到购物车中 产品的总数
	 */
	public function getCartItemsCount(){
		$cart =  $this->getCart();
		return $cart->items_count;
	}
	/**
	 * 返回当前的购物车Db对象
	 */
	/*
	public function getMyCart(){
		if(!$this->_my_cart){
			if($cart_id = $this->getCartId()){
				if(!$this->_my_cart){
					$this->_my_cart = MyCart::findOne(['cart_id'=>$cart_id]);
				}
			}else{
				$this->createCart();
			}
		}
		return $this->_my_cart;
	}
	*/
	
	/**
	 * @property $cart_id | int
	 * 设置cart_id类变量以及session中记录当前cartId的值
	 * Cart的session的超时时间由session组件决定。
	 */
	protected function actionSetCartId($cart_id){
		$this->_cart_id = $cart_id;
		
		Yii::$app->session->set(self::SESSION_CART_ID,$cart_id);
	}
	/**
	 * 清空购物车。只删除购物车中的产品，但是购物车中的信息保留。
	 */
	protected function actionClearCart(){
		Yii::$service->cart->quoteItem->removeItemByCartId();
		//Yii::$app->session->remove(self::SESSION_CART_ID);
	}
	/**
	 * 初始化创建cart信息，
	 * 在用户的第一个产品加入购物车时，会在数据库中创建购物车
	 */
	protected function actionCreateCart(){
		$myCart = new MyCart;
		$myCart->store = Yii::$service->store->currentStore;
		$myCart->created_at = time();
		$myCart->updated_at = time();
		if(!Yii::$app->user->isGuest){
			$identity 	= Yii::$app->user->identity;
			$id 		= $identity['id'];
			$firstname 	= $identity['firstname'];
			$lastname 	= $identity['lastname'];
			$email 		= $identity['email'];
			$myCart->customer_id 		= $id;
			$myCart->customer_email 	= $email;
			$myCart->customer_firstname = $firstname;
			$myCart->customer_lastname 	= $lastname;
			$myCart->customer_is_guest	= 2;
		}else{
			$myCart->customer_is_guest	= 1;
		}
		$myCart->remote_ip = \fec\helpers\CFunc::get_real_ip();
		$myCart->app_name  = Yii::$service->helper->getAppName();
		if($defaultShippingMethod = Yii::$service->shipping->getDefaultShippingMethod()){
			$myCart->shipping_method = $defaultShippingMethod;
		}
		$myCart->save();
		$cart_id = Yii::$app->db->getLastInsertId();
		$this->setCartId($cart_id);
		$this->setCart(MyCart::findOne($cart_id));
	}
	/*
	public function addCustomerDefautAddressToCart(){
		if(!Yii::$app->user->isGuest){
			$cart = $this->getCart();
			# 购物车没有customer address  id，则
			# 使用登录用户的默认address
			$identity = Yii::$app->user->identity;
			//echo $cart['customer_id'] ;
			//echo "##";
			//echo $identity['id'];
			//exit;
			if($cart['customer_id'] == $identity['id']){
				if(!isset($cart['customer_address_id']) || empty($cart['customer_address_id'])){
					$defaultAddress = Yii::$service->customer->address->getDefaultAddress();
					if(is_array($defaultAddress) && !empty($defaultAddress)){
						$cart->customer_telephone = isset($defaultAddress['telephone']) ? $defaultAddress['telephone'] : '';
						$cart->customer_email= isset($defaultAddress['email']) ? $defaultAddress['email'] : '';
						$cart->customer_firstname= isset($defaultAddress['first_name']) ? $defaultAddress['first_name'] : '';
						$cart->customer_lastname= isset($defaultAddress['last_name']) ? $defaultAddress['last_name'] : '';
						$cart->customer_address_id= isset($defaultAddress['address_id']) ? $defaultAddress['address_id'] : '';
						$cart->customer_address_country= isset($defaultAddress['country']) ? $defaultAddress['country'] : '';
						$cart->customer_address_state= isset($defaultAddress['state']) ? $defaultAddress['state'] : '';
						$cart->customer_address_city= isset($defaultAddress['city']) ? $defaultAddress['city'] : '';
						$cart->customer_address_zip= isset($defaultAddress['zip']) ? $defaultAddress['zip'] : '';
						$cart->customer_address_street1= isset($defaultAddress['street1']) ? $defaultAddress['street1'] : '';
						$cart->customer_address_street2= isset($defaultAddress['street2']) ? $defaultAddress['street2'] : '';
						$cart->save();
						$this->setCart($cart);
						
					}
				
				}
			}
		}
	}
	*/
	
	/**
	 * 购物车数据中是否含有address_id，address_id，是登录用户才会有的。
	 */
	 
	public function hasAddressId(){
		$cart = $this->getCart();
		$address_id = $cart['customer_address_id'];
		if($address_id){
			return true;
		}
	}
	/**
	 * 得到购物车中的用户地址信息
	 *
	 */
	public function getCartAddress(){
		$email = '';
		$first_name = '';
		$last_name = '';
		if(!Yii::$app->user->isGuest){
			$identity 	= Yii::$app->user->identity;
			$email 		= isset($identity['email']) ? $identity['email'] : '';
			$first_name = isset($identity['first_name']) ? $identity['first_name'] : '';
			$last_name  = isset($identity['last_name']) ? $identity['last_name'] : '';
		}
		$cart = $this->getCurrentCart();
		$customer_email = isset($cart['customer_email']) ? $cart['customer_email'] : '';
		$customer_firstname = isset($cart['customer_firstname']) ? $cart['customer_firstname'] : '';
		$customer_lastname = isset($cart['customer_lastname']) ? $cart['customer_lastname'] : '';
		$customer_telephone = isset($cart['customer_telephone']) ? $cart['customer_telephone'] : '';
		$customer_address_country = isset($cart['customer_address_country']) ? $cart['customer_address_country'] : '';
		$customer_address_state = isset($cart['customer_address_state']) ? $cart['customer_address_state'] : '';
		$customer_address_city = isset($cart['customer_address_city']) ? $cart['customer_address_city'] : '';
		$customer_address_zip = isset($cart['customer_address_zip']) ? $cart['customer_address_zip'] : '';
		$customer_address_street1 = isset($cart['customer_address_street1']) ? $cart['customer_address_street1'] : '';
		$customer_address_street2 = isset($cart['customer_address_street2']) ? $cart['customer_address_street2'] : '';
	
		$customer_email 	= $customer_email ? $customer_email : $email;
		$customer_firstname = $customer_firstname ? $customer_firstname : $first_name;
		$customer_lastname 	= $customer_lastname ? $customer_lastname : $last_name;
		return [
			'first_name' 	=> $customer_firstname,
			'last_name' 	=> $customer_lastname,
			'email' 		=> $customer_email,
			'telephone' 	=> $customer_telephone,
			'country' => $customer_address_country,
			'state' => $customer_address_state,
			'city' => $customer_address_city,
			'zip' => $customer_address_zip,
			'street1' => $customer_address_street1,
			'street2' => $customer_address_street2,
			
		];
	}
	
	/**
	 * @property $shipping_method | String  传递的货运方式
	 * @property $country | String 货运国家
	 * @property $region | String 省市
	 * @return boolean OR array ，如果存在问题返回false
	 * 如果没有问题，返回购物车的信息。
	 * 对于可选参数，如果不填写，就是返回当前的购物车的数据。
	 * 对于填写了参数，返回的是填写参数后的数据，这个一般是用户选择了了货运方式，国家等，然后
	 * 实时的计算出来数据反馈给用户，但是，用户选择的数据并没有进入cart表
	 */
	public function getCartInfo($shipping_method='',$country='',$region='*'){
		//echo 333;exit;
		$cartInfoKey = $shipping_method.'-shipping-'.$country.'-country-'.$region.'-region';
		if(!isset($this->cartInfo[$cartInfoKey])){
			$cart_id = $this->getCartId();
			if(!$cart_id){
				return false;
			}
			$cart = $this->getCart();
			
			$items_qty = $cart['items_count'];
			if($items_qty <= 0){
				return false;
			}
			//var_dump($cart);
			//echo "########".$cart['shipping_method'];
			$coupon_code = $cart['coupon_code'];
			if(!$shipping_method){
				$shipping_method = $cart['shipping_method'];
			}
			$cart_product_info = Yii::$service->cart->quoteItem->getCartProductInfo();
			if(is_array($cart_product_info)){
				$product_weight = $cart_product_info['product_weight'];
				
				$products = $cart_product_info['products'];
				$product_total = $cart_product_info['product_total'];
				$base_product_total = $cart_product_info['base_product_total'];
				if($products && $product_total){ 
					$currShippingCost = 0;
					$baseShippingCost = 0;
					if($shipping_method && $product_weight && $country){
						$shippingCost   = $this->getShippingCost($shipping_method,$product_weight,$country,$region);
						$currShippingCost = $shippingCost['currCost'];
						$baseShippingCost = $shippingCost['baseCost'];
					}
					//echo 333;
					//var_dump([$base_product_total,$product_total]);
					//exit;
					//echo $coupon_code;exit;
					$couponCost		= $this->getCouponCost($base_product_total,$coupon_code);
					
					$baseDiscountCost = $couponCost['baseCost'];
					$currDiscountCost = $couponCost['currCost'];
					
					$curr_grand_total	= $product_total + $currShippingCost - $currDiscountCost;
					$base_grand_total	= $base_product_total + $baseShippingCost - $baseDiscountCost;
					
					$this->cartInfo[$cartInfoKey] = [
						'store'			=> $cart['store'],				# store nme
						'items_count'	=> $cart['items_count'],		# 购物车中的产品总数
						'coupon_code'	=> $coupon_code,				# coupon卷码
						'shipping_method'	=> $shipping_method,
						'payment_method'	=> $cart['payment_method'],
						'grand_total' 	=> $curr_grand_total,			# 当前货币总金额
						'shipping_cost' => $currShippingCost,			# 当前货币，运费
						'coupon_cost' 	=> $currDiscountCost,			# 当前货币，优惠券优惠金额
						'product_total' => $product_total,				# 当前货币，购物车中产品的总金额
						
						'base_grand_total' 		=> $base_grand_total,	# 基础货币总金额
						'base_shipping_cost' 	=> $baseShippingCost,	# 基础货币，运费
						'base_coupon_cost' 		=> $baseDiscountCost,	# 基础货币，优惠券优惠金额
						'base_product_total' 	=> $base_product_total, # 基础货币，购物车中产品的总金额
						
						
						'products' 		=> $products,		#产品信息。
						'product_weight'=> $product_weight,	#产品的总重量。
					];
					
				}
				
			}
		}
		return $this->cartInfo[$cartInfoKey];
	}
	
	/**
	 * @property $shippingCost | Array ,example:
	 * 	[
	 *		'currCost'   => 33.22, #当前货币的运费金额
	 *		'baseCost'	=> 26.44,  #基础货币的运费金额
	 *	];
	 *  设置快递运费金额。
	 */
	 
	public function setShippingCost($shippingCost){
		$this->_shipping_cost = $shippingCost;
	}
	
	/**
	 * @property $shipping_method | String 货运方式
	 * @property $weight | Float 产品重量
	 * @property $country | String 国家
	 * @property $region | String 省/市
	 * @return $this->_shipping_cost | Array ,format:
	 * 	[
	 *		'currCost'   => 33.22, #当前货币的运费金额
	 *		'baseCost'	=> 26.44,  #基础货币的运费金额
	 *	];
	 *  得到快递运费金额。
	 */
	public function getShippingCost($shipping_method='',$weight='',$country='',$region=''){
		if(!$region){
			$region='*';
		}
		if(!$this->_shipping_cost){
			//echo "$shipping_method,$weight,$country,$region";
			$shippingCost = Yii::$service->shipping->getShippingCostWithSymbols($shipping_method,$weight,$country,$region);
			$this->_shipping_cost = $shippingCost;
			//if(isset($shippingCost['currentCost'])){
			//	$this->_shipping_cost = $shippingCost['currentCost'];
			//}
		}
		return $this->_shipping_cost;
	}
	/**
	 * 得到优惠券的折扣金额
	 * @return Array  , example:
	 * [
	 *	'baseCost' => $base_discount_cost, # 基础货币的优惠金额
	 *	'currCost' => $curr_discount_cost  # 当前货币的优惠金额
	 * ]
	 */
	public function getCouponCost($base_product_total,$coupon_code){
		//echo '###'; var_dump($product_total);exit;
		//list($base_product_total,$product_total) = $product_total;
		//$dc_price = Yii::$service->page->currency->getDefaultCurrencyPrice($product_total);
		$dc_discount = Yii::$service->cart->coupon->getDiscount($coupon_code,$base_product_total);
		//var_dump($dc_discount);exit;
		return $dc_discount;
	}
	/**
	 * @property $coupon_code | String
	 * 设置购物车的优惠券
	 */
	public function setCartCoupon($coupon_code){
		$cart = $this->getCart();
		$cart->coupon_code = $coupon_code;
		$cart->save();
		return true;
	}
	/**
	 * @property $coupon_code | String
	 * 取消购物车的优惠券
	 */
	public function cancelCartCoupon($coupon_code){
		$cart = $this->getCart();
		$cart->coupon_code = null;
		$cart->save();
		return true;
	}
	/**
	 * 当用户登录账号后，将用户未登录时的购物车和用户账号中保存
	 * 的购物车信息进行合并。
	 */
	public function mergeCartAfterUserLogin(){
		if(!Yii::$app->user->isGuest){
			$identity = Yii::$app->user->identity;
			$customer_id = $identity['id'];
			$email = $identity->email;
			$customer_firstname = $identity->firstname;
			$customer_lastname  = $identity->lastname;
			$customer_cart = $this->getCartByCustomerId($customer_id);
			$cart_id = $this->getCartId();
			if(!$customer_cart){
				if($cart_id){
					$cart = $this->getCart();
					if($cart){
						$cart['customer_email'] = $email ;
						$cart['customer_id'] = $customer_id ;
						$cart['customer_firstname'] = $customer_firstname ;
						$cart['customer_lastname'] = $customer_lastname ;
						$cart['customer_is_guest'] = 2;
						$cart->save();
					}
				}
			}else{
				$cart = $this->getCart();
				if(!$cart || !$cart_id){
					$cart_id = $customer_cart['cart_id'];
					$this->setCartId($cart_id);
				}else{
					# 将无用户产品（当前）和 购物车中的产品（登录用户对应的购物车）进行合并。
					$new_cart_id = $customer_cart['cart_id'];
					if($cart['coupon_code']){
						# 如果有优惠券则取消，以登录用户的购物车的优惠券为准。
						Yii::$service->cart->coupon->cancelCoupon($cart['coupon_code']);
					}
					# 将当前购物车产品表的cart_id 改成 登录用户对应的cart_id
					if($new_cart_id && $cart_id && ($new_cart_id != $cart_id)){
						Yii::$service->cart->quoteItem->updateCartId($new_cart_id,$cart_id);
						# 当前的购物车删除掉
						$cart->delete();
						# 设置当前的cart_id
						$this->setCartId($new_cart_id);
						# 设置当前的cart
						$this->setCart($customer_cart);
						# 重新计算购物车中产品的个数
						$this->computeCartInfo();
					}
				}
			}
		}
	}
	/**
	 * @property $customer_id | int
	 * @return MyCart Object。
	 * 通过用户的customer_id，在cart表中找到对应的购物车
	 */
	public function getCartByCustomerId($customer_id){
		if($customer_id){
			$one = MyCart::findOne(['customer_id' => $customer_id]);
			if($one['cart_id']){
				return $one;
			}
		}
	}
	
	
	
	
	
	
}