<?php
class WxPayDataBase
{
	public $values = array();
	
	/**
	* 设置签名，详见签名生成算法
	* @param string $value 
	**/
	public function SetSign()
	{
		$sign = $this->MakeSign();
		$this->values['sign'] = $sign;
		return $sign;
	}
	
	/**
	* 获取签名，详见签名生成算法的值
	* @return 值
	**/
	public function GetSign()
	{
		return $this->values['sign'];
	}
	
	/**
	* 判断签名，详见签名生成算法是否存在
	* @return true 或 false
	**/
	public function IsSignSet()
	{
		return array_key_exists('sign', $this->values);
	}

	/**
	 * 输出xml字符
	 * @throws WxPayException
	**/
	public function ToXml()
	{
		if(!is_array($this->values) 
			|| count($this->values) <= 0)
		{
    		Log::ERROR("数组数据异常！");
    	}
    	
    	$xml = "<xml>";
    	foreach ($this->values as $key=>$val)
    	{
    		if (is_numeric($val)){
    			$xml.="<".$key.">".$val."</".$key.">";
    		}else{
    			$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
    		}
        }
        $xml.="</xml>";
        return $xml; 
	}
	
    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	public function FromXml($xml)
	{	
		if(!$xml){
			Log::ERROR("xml数据异常！");
		}
        //将XML转为array 
        $this->values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);		
		return $this->values;
	}
	
	/**
	 * 格式化参数格式化成url参数
	 */
	public function ToUrlParams()
	{
		$buff = "";
		foreach ($this->values as $k => $v)
		{
			if($k != "sign" && $v != "" && !is_array($v)){
				$buff .= $k . "=" . $v . "&";
			}
		}
		
		$buff = trim($buff, "&");
		return $buff;
	}
	
	/**
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	public function MakeSign()
	{
		//签名步骤一：按字典序排序参数
		ksort($this->values);
		$string = $this->ToUrlParams();
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".PayKey;
		//签名步骤三：MD5加密
		$string = md5($string);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}
	
	/**
	 * 获取设置的值
	 */
	public function GetValues()
	{
		return $this->values;
	}

	public function SetData($key, $value)
	{
		$this->values[$key] = $value;
	}
}

/**
 * 
 * 接口调用结果类
 * @author widyhu
 *
 */
class WxPayResults extends WxPayDataBase
{
	/**
	 * 
	 * 检测签名
	 */
	public function CheckSign()
	{
		if(!$this->IsSignSet()){
			return true;
		}
		
		$sign = $this->MakeSign();
		if($this->GetSign() == $sign){
			return true;
		}
		Log::ERROR("签名错误！");
	}
	
	/**
	 * 
	 * 使用数组初始化
	 * @param array $array
	 */
	public function FromArray($array)
	{
		$this->values = $array;
	}
	
	/**
	 * 
	 * 使用数组初始化对象
	 * @param array $array
	 * @param 是否检测签名 $noCheckSign
	 */
	public static function InitFromArray($array, $noCheckSign = false)
	{
		$obj = new self();
		$obj->FromArray($array);
		if($noCheckSign == false){
			$obj->CheckSign();
		}
        return $obj;
	}
	
    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	public static function Init($xml)
	{	
		$obj = new self();
		$obj->FromXml($xml);
		$obj->CheckSign();
        return $obj->GetValues();
	}
}


class WxPayMicroPay extends WxPayDataBase
{
	/**
	* 设置微信分配的公众账号ID
	* @param string $value 
	**/
	public function SetAppid($value)
	{
		$this->values['appid'] = $value;
	}
	/**
	* 获取微信分配的公众账号ID的值
	* @return 值
	**/
	public function GetAppid()
	{
		return $this->values['appid'];
	}
	/**
	* 判断微信分配的公众账号ID是否存在
	* @return true 或 false
	**/
	public function IsAppidSet()
	{
		return array_key_exists('appid', $this->values);
	}


	/**
	* 设置微信支付分配的商户号
	* @param string $value 
	**/
	public function SetMch_id($value)
	{
		$this->values['mch_id'] = $value;
	}
	/**
	* 获取微信支付分配的商户号的值
	* @return 值
	**/
	public function GetMch_id()
	{
		return $this->values['mch_id'];
	}
	/**
	* 判断微信支付分配的商户号是否存在
	* @return true 或 false
	**/
	public function IsMch_idSet()
	{
		return array_key_exists('mch_id', $this->values);
	}


	/**
	* 设置终端设备号(商户自定义，如门店编号)
	* @param string $value 
	**/
	public function SetDevice_info($value)
	{
		$this->values['device_info'] = $value;
	}
	/**
	* 获取终端设备号(商户自定义，如门店编号)的值
	* @return 值
	**/
	public function GetDevice_info()
	{
		return $this->values['device_info'];
	}
	/**
	* 判断终端设备号(商户自定义，如门店编号)是否存在
	* @return true 或 false
	**/
	public function IsDevice_infoSet()
	{
		return array_key_exists('device_info', $this->values);
	}


	/**
	* 设置随机字符串，不长于32位。推荐随机数生成算法
	* @param string $value 
	**/
	public function SetNonce_str($value)
	{
		$this->values['nonce_str'] = $value;
	}
	/**
	* 获取随机字符串，不长于32位。推荐随机数生成算法的值
	* @return 值
	**/
	public function GetNonce_str()
	{
		return $this->values['nonce_str'];
	}
	/**
	* 判断随机字符串，不长于32位。推荐随机数生成算法是否存在
	* @return true 或 false
	**/
	public function IsNonce_strSet()
	{
		return array_key_exists('nonce_str', $this->values);
	}

	/**
	* 设置商品或支付单简要描述
	* @param string $value 
	**/
	public function SetBody($value)
	{
		$this->values['body'] = $value;
	}
	/**
	* 获取商品或支付单简要描述的值
	* @return 值
	**/
	public function GetBody()
	{
		return $this->values['body'];
	}
	/**
	* 判断商品或支付单简要描述是否存在
	* @return true 或 false
	**/
	public function IsBodySet()
	{
		return array_key_exists('body', $this->values);
	}


	/**
	* 设置商品名称明细列表
	* @param string $value 
	**/
	public function SetDetail($value)
	{
		$this->values['detail'] = $value;
	}
	/**
	* 获取商品名称明细列表的值
	* @return 值
	**/
	public function GetDetail()
	{
		return $this->values['detail'];
	}
	/**
	* 判断商品名称明细列表是否存在
	* @return true 或 false
	**/
	public function IsDetailSet()
	{
		return array_key_exists('detail', $this->values);
	}


	/**
	* 设置附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
	* @param string $value 
	**/
	public function SetAttach($value)
	{
		$this->values['attach'] = $value;
	}
	/**
	* 获取附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据的值
	* @return 值
	**/
	public function GetAttach()
	{
		return $this->values['attach'];
	}
	/**
	* 判断附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据是否存在
	* @return true 或 false
	**/
	public function IsAttachSet()
	{
		return array_key_exists('attach', $this->values);
	}


	/**
	* 设置商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号
	* @param string $value 
	**/
	public function SetOut_trade_no($value)
	{
		$this->values['out_trade_no'] = $value;
	}
	/**
	* 获取商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号的值
	* @return 值
	**/
	public function GetOut_trade_no()
	{
		return $this->values['out_trade_no'];
	}
	/**
	* 判断商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号是否存在
	* @return true 或 false
	**/
	public function IsOut_trade_noSet()
	{
		return array_key_exists('out_trade_no', $this->values);
	}


	/**
	* 设置订单总金额，单位为分，只能为整数，详见支付金额
	* @param string $value 
	**/
	public function SetTotal_fee($value)
	{
		$this->values['total_fee'] = $value;
	}
	/**
	* 获取订单总金额，单位为分，只能为整数，详见支付金额的值
	* @return 值
	**/
	public function GetTotal_fee()
	{
		return $this->values['total_fee'];
	}
	/**
	* 判断订单总金额，单位为分，只能为整数，详见支付金额是否存在
	* @return true 或 false
	**/
	public function IsTotal_feeSet()
	{
		return array_key_exists('total_fee', $this->values);
	}


	/**
	* 设置符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型
	* @param string $value 
	**/
	public function SetFee_type($value)
	{
		$this->values['fee_type'] = $value;
	}
	/**
	* 获取符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型的值
	* @return 值
	**/
	public function GetFee_type()
	{
		return $this->values['fee_type'];
	}
	/**
	* 判断符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型是否存在
	* @return true 或 false
	**/
	public function IsFee_typeSet()
	{
		return array_key_exists('fee_type', $this->values);
	}


	/**
	* 设置调用微信支付API的机器IP 
	* @param string $value 
	**/
	public function SetSpbill_create_ip($value)
	{
		$this->values['spbill_create_ip'] = $value;
	}
	/**
	* 获取调用微信支付API的机器IP 的值
	* @return 值
	**/
	public function GetSpbill_create_ip()
	{
		return $this->values['spbill_create_ip'];
	}
	/**
	* 判断调用微信支付API的机器IP 是否存在
	* @return true 或 false
	**/
	public function IsSpbill_create_ipSet()
	{
		return array_key_exists('spbill_create_ip', $this->values);
	}


	/**
	* 设置订单生成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010。详见时间规则
	* @param string $value 
	**/
	public function SetTime_start($value)
	{
		$this->values['time_start'] = $value;
	}
	/**
	* 获取订单生成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010。详见时间规则的值
	* @return 值
	**/
	public function GetTime_start()
	{
		return $this->values['time_start'];
	}
	/**
	* 判断订单生成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010。详见时间规则是否存在
	* @return true 或 false
	**/
	public function IsTime_startSet()
	{
		return array_key_exists('time_start', $this->values);
	}


	/**
	* 设置订单失效时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。详见时间规则
	* @param string $value 
	**/
	public function SetTime_expire($value)
	{
		$this->values['time_expire'] = $value;
	}
	/**
	* 获取订单失效时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。详见时间规则的值
	* @return 值
	**/
	public function GetTime_expire()
	{
		return $this->values['time_expire'];
	}
	/**
	* 判断订单失效时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。详见时间规则是否存在
	* @return true 或 false
	**/
	public function IsTime_expireSet()
	{
		return array_key_exists('time_expire', $this->values);
	}


	/**
	* 设置商品标记，代金券或立减优惠功能的参数，说明详见代金券或立减优惠
	* @param string $value 
	**/
	public function SetGoods_tag($value)
	{
		$this->values['goods_tag'] = $value;
	}
	/**
	* 获取商品标记，代金券或立减优惠功能的参数，说明详见代金券或立减优惠的值
	* @return 值
	**/
	public function GetGoods_tag()
	{
		return $this->values['goods_tag'];
	}
	/**
	* 判断商品标记，代金券或立减优惠功能的参数，说明详见代金券或立减优惠是否存在
	* @return true 或 false
	**/
	public function IsGoods_tagSet()
	{
		return array_key_exists('goods_tag', $this->values);
	}


	/**
	* 设置扫码支付授权码，设备读取用户微信中的条码或者二维码信息
	* @param string $value 
	**/
	public function SetAuth_code($value)
	{
		$this->values['auth_code'] = $value;
	}
	/**
	* 获取扫码支付授权码，设备读取用户微信中的条码或者二维码信息的值
	* @return 值
	**/
	public function GetAuth_code()
	{
		return $this->values['auth_code'];
	}
	/**
	* 判断扫码支付授权码，设备读取用户微信中的条码或者二维码信息是否存在
	* @return true 或 false
	**/
	public function IsAuth_codeSet()
	{
		return array_key_exists('auth_code', $this->values);
	}
}


class WxPayJsApiPay extends WxPayDataBase
{
	/**
	* 设置微信分配的公众账号ID
	* @param string $value 
	**/
	public function SetAppid($value)
	{
		$this->values['appId'] = $value;
	}
	/**
	* 获取微信分配的公众账号ID的值
	* @return 值
	**/
	public function GetAppid()
	{
		return $this->values['appId'];
	}
	/**
	* 判断微信分配的公众账号ID是否存在
	* @return true 或 false
	**/
	public function IsAppidSet()
	{
		return array_key_exists('appId', $this->values);
	}


	/**
	* 设置支付时间戳
	* @param string $value 
	**/
	public function SetTimeStamp($value)
	{
		$this->values['timeStamp'] = $value;
	}
	/**
	* 获取支付时间戳的值
	* @return 值
	**/
	public function GetTimeStamp()
	{
		return $this->values['timeStamp'];
	}
	/**
	* 判断支付时间戳是否存在
	* @return true 或 false
	**/
	public function IsTimeStampSet()
	{
		return array_key_exists('timeStamp', $this->values);
	}
	
	/**
	* 随机字符串
	* @param string $value 
	**/
	public function SetNonceStr($value)
	{
		$this->values['nonceStr'] = $value;
	}
	/**
	* 获取notify随机字符串值
	* @return 值
	**/
	public function GetReturn_code()
	{
		return $this->values['nonceStr'];
	}
	/**
	* 判断随机字符串是否存在
	* @return true 或 false
	**/
	public function IsReturn_codeSet()
	{
		return array_key_exists('nonceStr', $this->values);
	}


	/**
	* 设置订单详情扩展字符串
	* @param string $value 
	**/
	public function SetPackage($value)
	{
		$this->values['package'] = $value;
	}
	/**
	* 获取订单详情扩展字符串的值
	* @return 值
	**/
	public function GetPackage()
	{
		return $this->values['package'];
	}
	/**
	* 判断订单详情扩展字符串是否存在
	* @return true 或 false
	**/
	public function IsPackageSet()
	{
		return array_key_exists('package', $this->values);
	}
	
	/**
	* 设置签名方式
	* @param string $value 
	**/
	public function SetSignType($value)
	{
		$this->values['signType'] = $value;
	}
	/**
	* 获取签名方式
	* @return 值
	**/
	public function GetSignType()
	{
		return $this->values['signType'];
	}
	/**
	* 判断签名方式是否存在
	* @return true 或 false
	**/
	public function IsSignTypeSet()
	{
		return array_key_exists('signType', $this->values);
	}
	
	/**
	* 设置签名方式
	* @param string $value 
	**/
	public function SetPaySign($value)
	{
		$this->values['paySign'] = $value;
	}
	/**
	* 获取签名方式
	* @return 值
	**/
	public function GetPaySign()
	{
		return $this->values['paySign'];
	}
	/**
	* 判断签名方式是否存在
	* @return true 或 false
	**/
	public function IsPaySignSet()
	{
		return array_key_exists('paySign', $this->values);
	}
}

class WxPayBizPayUrl extends WxPayDataBase
{
	/**
	* 设置微信分配的公众账号ID
	* @param string $value 
	**/
	public function SetAppid($value)
	{
		$this->values['appid'] = $value;
	}
	/**
	* 获取微信分配的公众账号ID的值
	* @return 值
	**/
	public function GetAppid()
	{
		return $this->values['appid'];
	}
	/**
	* 判断微信分配的公众账号ID是否存在
	* @return true 或 false
	**/
	public function IsAppidSet()
	{
		return array_key_exists('appid', $this->values);
	}


	/**
	* 设置微信支付分配的商户号
	* @param string $value 
	**/
	public function SetMch_id($value)
	{
		$this->values['mch_id'] = $value;
	}
	/**
	* 获取微信支付分配的商户号的值
	* @return 值
	**/
	public function GetMch_id()
	{
		return $this->values['mch_id'];
	}
	/**
	* 判断微信支付分配的商户号是否存在
	* @return true 或 false
	**/
	public function IsMch_idSet()
	{
		return array_key_exists('mch_id', $this->values);
	}
	
	/**
	* 设置支付时间戳
	* @param string $value 
	**/
	public function SetTime_stamp($value)
	{
		$this->values['time_stamp'] = $value;
	}
	/**
	* 获取支付时间戳的值
	* @return 值
	**/
	public function GetTime_stamp()
	{
		return $this->values['time_stamp'];
	}
	/**
	* 判断支付时间戳是否存在
	* @return true 或 false
	**/
	public function IsTime_stampSet()
	{
		return array_key_exists('time_stamp', $this->values);
	}
	
	/**
	* 设置随机字符串
	* @param string $value 
	**/
	public function SetNonce_str($value)
	{
		$this->values['nonce_str'] = $value;
	}
	/**
	* 获取随机字符串的值
	* @return 值
	**/
	public function GetNonce_str()
	{
		return $this->values['nonce_str'];
	}
	/**
	* 判断随机字符串是否存在
	* @return true 或 false
	**/
	public function IsNonce_strSet()
	{
		return array_key_exists('nonce_str', $this->values);
	}
	
	/**
	* 设置商品ID
	* @param string $value 
	**/
	public function SetProduct_id($value)
	{
		$this->values['product_id'] = $value;
	}
	/**
	* 获取商品ID的值
	* @return 值
	**/
	public function GetProduct_id()
	{
		return $this->values['product_id'];
	}
	/**
	* 判断商品ID是否存在
	* @return true 或 false
	**/
	public function IsProduct_idSet()
	{
		return array_key_exists('product_id', $this->values);
	}
}

class WxPayApi
{
	public static function transfers($orderid, $openid, $amount, $desc) {
		$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";

		$wxpaydata = new WxPayDataBase;
		$wxpaydata->values = array(
			'mch_appid' => AppID,
			'mchid' => MchID,
			'nonce_str' => WxPayApi::getNonceStr(),
			'partner_trade_no' => $orderid,
			'openid' => $openid,
			'check_name' => 'NO_CHECK',
			'amount' => $amount, 
			'desc' => $desc,
			'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
		);

		$wxpaydata->SetSign();

		$xml = $wxpaydata->ToXml();

		$data = WxPayApi::postXmlCurl($xml, $url, true);

		return WxPayResults::Init($data);
	}
	/**
	 * 
	 * 统一下单
	 * @param WxPayUnifiedOrder $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function unifiedOrder($inputObj, $timeOut = 6, $platform = null)
	{
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
		//检测必填参数
		if(!$inputObj->values['out_trade_no']) {
			Log::ERROR("缺少统一支付接口必填参数out_trade_no！");
		}else if(!$inputObj->values['body']){
			Log::ERROR("缺少统一支付接口必填参数body！");
		}else if(!$inputObj->values['total_fee']) {
			Log::ERROR("缺少统一支付接口必填参数total_fee！");
		}else if(!$inputObj->values['trade_type']) {
			Log::ERROR("缺少统一支付接口必填参数trade_type！");
		}
		
		//关联参数
		if($inputObj->values['trade_type'] == "JSAPI" && !$inputObj->values['openid']){
			Log::ERROR("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
		}
		if($inputObj->values['trade_type'] == "NATIVE" && !$inputObj->values['product_id']){
			Log::ERROR("统一支付接口中，缺少必填参数product_id！trade_type为NATIVE时，product_id为必填参数！");
		}
		
		//异步通知url未设置，则使用配置文件中的url
		if(!$inputObj->values['notify_url']){
			$inputObj->values['notify_url'] = 'http://paysdk.weixin.qq.com/notify.php';//异步通知url
		}
		
		$inputObj->values['appid'] = ($platform == PLATFORM_WEAPP) ? WEAPP_APP_ID : AppID;//判断appid是小程序还是公众账号
		$inputObj->values['mch_id'] = MchID;//商户号
		$inputObj->values['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];//终端ip	  
		$inputObj->values['nonce_str'] = self::getNonceStr();//随机字符串
		
		//签名
		$inputObj->SetSign();
		$xml = $inputObj->ToXml(); 
		
		//$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($xml, $url, false, $timeOut); //Log::INFO($response); return;
		$result = WxPayResults::Init($response);
		//self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 *
	 * 申请退款
	 * @param WxPayRefund $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function refund($inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
		//检测必填参数
		if(!$inputObj->values['out_trade_no'] && !$inputObj->values['transaction_id']) {
			Log::ERROR("退款申请接口中，out_trade_no、transaction_id至少填一个！");
		}else if(!$inputObj->values['out_refund_no']){
			Log::ERROR("退款申请接口中，缺少必填参数out_refund_no！");
		}else if(!$inputObj->values['total_fee']){
			Log::ERROR("退款申请接口中，缺少必填参数total_fee！");
		}else if(!$inputObj->values['refund_fee']){
			Log::ERROR("退款申请接口中，缺少必填参数refund_fee！");
		}else if(!$inputObj->values['op_user_id']){
			Log::ERROR("退款申请接口中，缺少必填参数op_user_id！");
		}
		$inputObj->values['appid'] = AppID;//公众账号ID
		$inputObj->values['mch_id'] = MchID;//商户号
		$inputObj->values['nonce_str'] = self::getNonceStr();//随机字符串

		$inputObj->SetSign();//签名
		$xml = $inputObj->ToXml();
		//$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($xml, $url, true, $timeOut);
		$result = WxPayResults::Init($response);
		//self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

		return $result;
	}

	/**
	 * 提交被扫支付API
	 * 收银员使用扫码设备读取微信用户刷卡授权码以后，二维码或条码信息传送至商户收银台，
	 * 由商户收银台或者商户后台调用该接口发起支付。
	 * @param WxPayWxPayMicroPay $inputObj
	 * @param int $timeOut
	 */
	public static function micropay($inputObj, $timeOut = 10)
	{
		$url = "https://api.mch.weixin.qq.com/pay/micropay";
		//检测必填参数
		if(!$inputObj->IsBodySet()) {
			Log::ERROR("提交被扫支付API接口中，缺少必填参数body！");
		} else if(!$inputObj->IsOut_trade_noSet()) {
			Log::ERROR("提交被扫支付API接口中，缺少必填参数out_trade_no！");
		} else if(!$inputObj->IsTotal_feeSet()) {
			Log::ERROR("提交被扫支付API接口中，缺少必填参数total_fee！");
		} else if(!$inputObj->IsAuth_codeSet()) {
			Log::ERROR("提交被扫支付API接口中，缺少必填参数auth_code！");
		}
		
		$inputObj->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
		$inputObj->SetAppid(AppID);//公众账号ID
		$inputObj->SetMch_id(MchID);//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign();//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($xml, $url, false, $timeOut);
		$result = WxPayResults::Init($response);
		self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 
	 * 生成二维码规则,模式一生成支付二维码
	 * @param WxPayBizPayUrl $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function bizpayurl($inputObj, $timeOut = 6)
	{
		if(!$inputObj->IsProduct_idSet()){
			Log::ERROR("生成二维码，缺少必填参数product_id！");
		}
		
		$inputObj->SetAppid(AppID);//公众账号ID
		$inputObj->SetMch_id(MchID);//商户号
		$inputObj->SetTime_stamp(time());//时间戳	 
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign();//签名
		
		return $inputObj->GetValues();
	}
	
	/**
	 * 
	 * 转换短链接
	 * 该接口主要用于扫码原生支付模式一中的二维码链接转成短链接(weixin://wxpay/s/XXXXXX)，
	 * 减小二维码数据量，提升扫描速度和精确度。
	 * @param WxPayShortUrl $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function shorturl($inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/tools/shorturl";
		//检测必填参数
		if(!$inputObj->IsLong_urlSet()) {
			Log::ERROR("需要转换的URL，签名用原串，传输需URL encode！");
		}
		$inputObj->SetAppid(AppID);//公众账号ID
		$inputObj->SetMch_id(MchID);//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign();//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($xml, $url, false, $timeOut);
		$result = WxPayResults::Init($response);
		self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public static function getNonceStr($length = 32) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return $str;
	}
	
	/**
	 * 以post方式提交xml到对应的接口url
	 * Enter description here ...
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	public static function postXmlCurl($xml, $url, $useCert = false, $second = 30)
	{		
        //初始化curl        
       	$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		
        //如果有配置代理这里就设置代理
		if(WXPAY_CURL_PROXY_HOST != "0.0.0.0" 
			&& WXPAY_CURL_PROXY_PORT != 0){
			curl_setopt($ch,CURLOPT_PROXY, WXPAY_CURL_PROXY_HOST);
			curl_setopt($ch,CURLOPT_PROXYPORT, WXPAY_CURL_PROXY_PORT);
		}
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, WXPAY_SSLCERT_PATH);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, WXPAY_SSLKEY_PATH);
		}
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
        $data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			Log::ERROR("curl出错，错误码:$error");
		}
	}
	
	/**
	 * 获取毫秒级别的时间戳
	 */
	private static function getMillisecond()
	{
		//获取毫秒的时间戳
		$time = explode ( " ", microtime () );
		$time = $time[1] . ($time[0] * 1000);
		$time2 = explode( ".", $time );
		$time = $time2[0];
		return $time;
	}
}

class JsApiPay
{
	public function GetOpenid($wxAuthHost = NULL)
	{
		//通过code获得openid
		if (!isset($_GET['code'])){ 
			//触发微信返回code码
			$baseUrl = urlencode('http://'.($wxAuthHost? : $_SERVER['HTTP_HOST']).$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
			$url = $this->__CreateOauthUrlForCode($baseUrl);
			Header("Location: $url");
			exit();
		} else { 
			//获取code码，以获取openid
		    $code = $_GET['code'];
			$openid = $this->GetOpenidFromMp($code);
			return $openid;
		}
	}
	
	public function GetJsApiParameters($UnifiedOrderResult)
	{
		if(!array_key_exists("appid", $UnifiedOrderResult)
		|| !array_key_exists("prepay_id", $UnifiedOrderResult)
		|| $UnifiedOrderResult['prepay_id'] == "")
		{
			return json_encode($UnifiedOrderResult);
		}
		$jsapi = new WxPayJsApiPay();
		$jsapi->SetAppid($UnifiedOrderResult["appid"]);
		$timeStamp = time();
		$jsapi->SetTimeStamp("$timeStamp");
		$jsapi->SetNonceStr(WxPayApi::getNonceStr());
		$jsapi->SetPackage("prepay_id=" . $UnifiedOrderResult['prepay_id']);
		$jsapi->SetSignType("MD5");
		$jsapi->SetPaySign($jsapi->MakeSign());
		$parameters = json_encode($jsapi->GetValues());
		return $parameters;
	}
	
	public function GetOpenidFromMp($code)
	{
		$url = $this->__CreateOauthUrlForOpenid($code);
		//初始化curl
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30s最大执行时间
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		if(WXPAY_CURL_PROXY_HOST != "0.0.0.0" 
			&& WXPAY_CURL_PROXY_PORT != 0){
			curl_setopt($ch,CURLOPT_PROXY, WXPAY_CURL_PROXY_HOST);
			curl_setopt($ch,CURLOPT_PROXYPORT, WXPAY_CURL_PROXY_PORT);
		}
		//运行curl，结果以jason形式返回
		$res = curl_exec($ch);
		curl_close($ch);
		//取出openid
		$data = json_decode($res,true);
		if(isset($data['errcode'])){
		    return $data;
        }
		$openid = $data['openid'];
		return $openid;
	}
	
	private function ToUrlParams($urlObj)
	{
		$buff = "";
		foreach ($urlObj as $k => $v)
		{
			if($k != "sign"){
				$buff .= $k . "=" . $v . "&";
			}
		}
		
		$buff = trim($buff, "&");
		return $buff;
	}
	
	private function __CreateOauthUrlForCode($redirectUrl)
	{
		$urlObj["appid"] = AppID;
		$urlObj["redirect_uri"] = "$redirectUrl";
		$urlObj["response_type"] = "code";
		$urlObj["scope"] = "snsapi_base";
		$urlObj["state"] = "STATE"."#wechat_redirect";
		$bizString = $this->ToUrlParams($urlObj);
		return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
	}
	
	private function __CreateOauthUrlForOpenid($code)
	{
		$urlObj["appid"] = AppID;
		$urlObj["secret"] = AppSecret;
		$urlObj["code"] = $code;
		$urlObj["grant_type"] = "authorization_code";
		$bizString = $this->ToUrlParams($urlObj);
		return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
	}
}
