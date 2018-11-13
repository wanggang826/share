<?php
/**
 * ALIPAY API: alipay.databiz.core.user.level.get request
 *
 * @author auto create
 * @since 1.0, 2014-10-10 16:50:26
 */
class ZhimaMerchantOrderRentQueryRequest
{
	/** 
	 * 业务参数
	 **/
	/*private $outOrderNo;
	private $productCode;
	*/
	private $bizContent;

	private $apiParas = array();
	private $terminalType;
	private $terminalInfo;
	private $prodCode;
	private $apiVersion="1.1";
	private $notifyUrl;

	public function setBizContent($bizContent)
	{
		$this->bizContent = $bizContent;
		$this->apiParas["biz_content"] = $bizContent;
	}

	public function getBizContent()
	{
		return $this->bizContent;
	}

	/*
	public function setOutOrderNo($outOrderNo) {
		$this->outOrderNo = $outOrderNo;
		$this->apiParas["out_order_no"] = $outOrderNo;
	}

	public function getOutOrderNo() {
		return $this->outOrderNo;
	}

	public function setProductCode($productCode) {
		$this->productCode = $productCode;
		$this->apiParas["product_code"] = $productCode;
	}

	public function getProductCode() {
		return $this->productCode;
	}
	*/

	public function getApiMethodName()
	{
		return "zhima.merchant.order.rent.query";
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
	
	public function setNotifyUrl($notifyUrl)
	{
		$this->notifyUrl=$notifyUrl;
	}
	
	public function getNotifyUrl()
	{
		return $this->notifyUrl;
	}

}
