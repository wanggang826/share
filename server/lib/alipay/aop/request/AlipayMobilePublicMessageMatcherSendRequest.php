<?php
/**
 * ALIPAY API: alipay.mobile.public.message.matcher.send request
 *
 * @author auto create
 * @since 1.0, 2014-07-25 14:58:25
 */
class AlipayMobilePublicMessageMatcherSendRequest
{
	/** 
	 * 业务内容json，其中包括匹配器matcher和模板template两大块，注意这里的userId仅接受2088开头，样例如下
{
	"matcher": {
		"mobileNo": "15869106831",
		"userId": "2088102118109919"
	},	
	"template": {
		"context": {
			"amount": "20.00元",
			"balance": "5000",
			"cardNo": "1855",
			"date": "04年10月18:06",
			"tradeType": "支付宝消费"
		},
		"templateId": "1ff2840464c4463187f5451119de8fea"
	}		
}
	 **/
	private $bizContent;

	private $apiParas = array();
	private $terminalType;
	private $terminalInfo;
	private $prodCode;
	private $apiVersion="1.0";
	
	public function setBizContent($bizContent)
	{
		$this->bizContent = $bizContent;
		$this->apiParas["biz_content"] = $bizContent;
	}

	public function getBizContent()
	{
		return $this->bizContent;
	}

	public function getApiMethodName()
	{
		return "alipay.mobile.public.message.matcher.send";
	}

	public function getApiParas()
	{
		return $this->apiParas;
	}

	public function getTerminalType()
	{
		return $this->terminalType;
	}

	public function setTerminalType($terminalType)
	{
		$this->terminalType = $terminalType;
	}

	public function getTerminalInfo()
	{
		return $this->terminalInfo;
	}

	public function setTerminalInfo($terminalInfo)
	{
		$this->terminalInfo = $terminalInfo;
	}

	public function getProdCode()
	{
		return $this->prodCode;
	}

	public function setProdCode($prodCode)
	{
		$this->prodCode = $prodCode;
	}

	public function setApiVersion($apiVersion)
	{
		$this->apiVersion=$apiVersion;
	}

	public function getApiVersion()
	{
		return $this->apiVersion;
	}

}
