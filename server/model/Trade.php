<?php
namespace model;

use \C;
use \Exception;

/*
	Trade 是商品的抽象类,管理的是一堆商品
	下面是Trade的一些用法
	$trade = new Trade ();  // 新建一个空商品对象
	// $trade = new Trade(2); // 初始化 tid=2 的一个商品对象
	// $trade = new Trade([1,2,3]); // 初始化 tid 为 1,2,3 的一个商品对象
	// $trade -> curent_trades();  // 得到当前的商品对象
*/
class Trade extends Model
{
	const TRADE_TYPE_SELL = 0;
	const TRADE_TYPE_RENT = 1;

	private $ids = [] ;    // 批量操作商品 的 id 集合 如 [id1 => [商品详情...],id2 => []...]

	private $page_size = 10;   // 第 几 页数据

	private $count;   // 数据表中总商品数

	private $menu;    // menu表对象

	private $trade;    // trade表对象

	private $tradelog;	// trade_log 对象

	public function __construct($con = null)
	{
		if(is_numeric($con)){
			$this -> ids[$con] = [];
		}
		if(is_array($con)){
			foreach ($con as $value) {
				$this -> ids[$value] = [] ;
			}
		}
		$this -> menu = ct('menu');
		$this -> tradelog = ct('tradelog');
		$this->_init();
	}

	/*
	 *  设置商品目录
	 *  param1   ['id'=>'','name'=>'','pre_category_id'=>'','icon'="",'status'=""]
	 *  键值任意
	 * */
	public function category($con)
	{
		$data = [];
		$keys = ['id','name','pre_category_id','icon','status'];
		foreach ($keys as $key){
			if(isset($con[$key])){
				$data[$key] = $con[$key];
			}
		}
		if(isset($con['id'])){
			return $this -> category -> update($con['id'],$data);
		}else{
			return $this -> category -> insert($data,false,true);
		}
	}

	/*
	 * 商品分类目录 总数
	 * */
	public function category_count()
	{
		return $this->category->count();
	}

	/*
	 * 获取 category 通过 id
	 * */
	public function fetch_categorys($ids)
	{
		return $this ->  category -> fetch_all($ids);
	}

	/*
	 * 分页获取商品分类
	 * */
	public function all_category($page,$page_size = null)
	{
		if($page_size){
			$limit = $page_size;
		}else{
			$limit = RECORD_LIMIT_PER_PAGE;
		}
		$start = ( $page - 1 ) * $limit;
		$sort  = 'id asc';
		$categorys = $this -> category -> range($start,$limit,$sort);
		return $categorys;
	}

	/*
		当前管理的商品
	*/
	public function current_trades()
	{
		return $this->ids;
	}

	/*
	 * 返回数据表中商品总数
	 * */
	public function count()
	{
		$this -> count = ct('menu') -> count();
		return $this->count;
	}

	/*
		初始化商品对象
	*/
	protected function _init()
	{
		if(count($this->ids) > 0){
			foreach ($this->ids as $key => $value) {
				$menu = ct('menu')->fetch($key);
// 				$trade= ct('trade')->fetch($key);
// 				$product = array_merge($menu,$trade);
				$product = $menu;
				$product['message'] = unserialize($product['message']);
				$this->ids[$key] = $product;
			}
		}
	}

	/*
	 * 获取一个商品
	 * */
	public function get_menu_by_id($tid)
	{
		return $this -> menu -> fetch_all($tid)[$tid];
// 		if($ret){
// 			$res = $this -> trade -> fetch_all($tid)[$tid];
// 			$menu = array_merge($ret,$res);
// 			return $menu;
// 		}
// 		return false;
	}

	/*
	 加载第 n 页的商品
	 */
	public function load_page($page,$page_size = 10)
	{
		$tid = $keywords = $start = $limit = null ;
		$invisible = '';
		$start = ( $page - 1 ) * $page_size;
		$limit = $page_size;

		$posts = ct('menu')->fetch_all_by_search( 0, $tid, $keywords, $invisible, $start, $limit );
		foreach ($posts as $post) {
// 			$trade = ct('trade')->fetch_goods($post['tid']);
// 			$product = array_merge($post,$trade);
			$product = $post;
			$product['message'] = unserialize($product['message']);
			$product['carousel'] = json_decode($product['carousel']);
			$cid = $product['category_id'];
			$ret = $this -> fetch_categorys($cid);
			if(!empty($ret)){
				$category = $ret[$cid];
				unset($category['id']);
				$product = array_merge($product,$category);
			}
			$items[$product['tid']] = $product;
		}
		return $items;
	}

	/*
	 * 新增一个商品
	 * */
	public function new_menu($con = [])
	{
		// 写入 menu 表
		$menu = $this->get_key_selected_array(['subject','descri','price','costprice'],$con);
		$menu['invisible'] = 0;
		$menu['trade_type'] = self::TRADE_TYPE_SELL;
		$menu['tags'] = 0;
		$tid = ct('menu')->insert($menu,true);

		// 写入 trade 表
// 		if($tid){
// 			$trade = $this->get_key_selected_array(['subject',], $con);
// 			$trade['tid'] = $tid;
// 			$trade['amount'] = 0;
// 			$trade['quantity'] = 0;
// 			$trade['discount'] = ORG_DISC_RATIO;
// 			$trade['soldrate'] = ORG_SOLD_RATE;
// 			$tid2 = ct('trade')->insert($trade,true);
// 			if($tid2){
// 				return true;
// 			}
// 		}
		return true;
	}

	/*
	 * 更新一个商品轮播图
	 * */
	public function update_menu_carousel($con = [])
	{
		$menu = $this->get_key_selected_array(['carousel'],$con);
		$tid = ct('menu')->update($con['id'],$menu);
		return $tid;
	}

	/*
	 * 更新一个商品轮播图
	 * */
	public function update_menu_category($con = [])
	{
		$menu = $this->get_key_selected_array(['category_id'],$con);
		return ct('menu')->update($con['id'],$menu);
	}

	/*
	 * 更新商品
	 * */
	public function update_menu($con)
	{
		// 修改 menu 表
		$menu = $this->get_key_selected_array(['subject','descri','trade_type','price','costprice'],$con);
		if(!empty($menu)){
			$this -> menu -> update($con['tid'],$menu);
		}

		// 修改 trade 表
// 		$trade = $this->get_key_selected_array(['subject'], $con);
// 		if(!empty($trade)){
// 			$this -> trade -> update($con['tid'],$trade);
// 		}
		return true;
	}

	/*
		 设置一个商品 (废弃)
		 $con 是包含商品信息的一个数组
		 $con 必须存在的键 ['subject','trade_type','desc','price','costprice','mat0']
	*/
	public function set(Array $con)
	{
		// check array
		if(!is_array($con)){
				throw new \Exception("传参要求为数组");
				return ;
		}
		// check must key
		$con_key = ['subject','trade_type','desc','price','costprice','mat0'];
		foreach ($con_key as $key) {
			if(!isset($con[$key])){
				throw new \Exception("set(Array $con) do not give the key which the function need");
				return ;
			}
		}

		$message = serialize(array(
			'desc' => $con['desc'],
			'imgs' => array_slice($con['mat0'], 0),
		));

		// tags 是为了兼容老代码写的 ，实际没什么用
		$tags = '111';   // default tags
		if($con['trade_type'] == self::TRADE_TYPE_SELL){
				$tags = "333";
		}

		$tid = ct('menu')->insert(array(
				'subject' => $con['subject'],
				'message' => $message,
				'tags'    => $tags,
				'invisible' => 0,
				'trade_type' => $con['trade_type'],
				'price' => $con['price'],
				'amount' => 0, // 废弃字段
				'discount' => ORG_DISC_RATIO,
				'costprice' => $con['costprice'],
				'quantity' => 0,  // 废弃字段
				'soldrate' => ORG_SOLD_RATE
				
		), true);

		return $tid;
	}

	/*
		返回商品的所有标签
	*/
	public function tags()
	{
		$tags = ct('tag') -> range(0,100,'id desc');
		return $tags;
	}

	/*

	*/
	public function menu_get_by($k,$v)
	{
		return $this -> menu -> fetch_all_by_field($k,$v);
	}

	public function category_get_by($k,$v)
	{
		return $this -> category -> fetch_all_by_field($k,$v);
	}

	/*
		检查传参数组是否包含必须有的键值对
	*/
	public static function _check_must_key($con,$key_arr)
	{
		foreach($key_arr as $key){
			if(!isset($con[$key])){
				throw new Exception("少传了必须传的参数");
				return ;
			}
		}
		return true;
	}

	/*
		获取用户的订单总数
	*/
	public function order_count_by_uid($uid)
	{
		return $this -> tradelog -> order_count_by_uid($uid);
	}

	/*
		获取用户的消费总数
	*/
	public function usefee_count_by_uid($uid)
	{
		$usefee_count = $this -> tradelog -> usefee_count_by_uid($uid);
		if(!$usefee_count){
			return '0.00';
		}
		return $usefee_count;
	}

	public function outstanding_order_count($uid)
	{
		$outstanding_order_count = $this -> tradelog -> outstanding_order_count($uid);
		
		if(!$outstanding_order_count){
			return 0;
		}
		return $outstanding_order_count;
	}

}
// end of file Trade.php
