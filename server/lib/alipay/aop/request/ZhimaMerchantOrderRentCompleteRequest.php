<?php
/**
 * ALIPAY API: alipay.databiz.core.user.level.get request
 *
 * @author auto create
 * @since 1.0, 2014-10-10 16:50:26
 */
class ZhimaMerchantOrderRentCompleteRequest
{
	/** 
	 * 业务参数
	 **/
	/*
	private $orderNo;
	private $productCode;
	private $restoreTime;
	private $payAmountType;
	private $payAmount;
	private $restoreShopName;
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
	public function setOrderNo($orderNo) {
		$this->orderNo = $orderNo;
		$this->apiParas["order_no"] = $orderNo;
	}

	public function getOrderNo() {
		return $this->orderNo;
	}

	public function setProductCode($productCode) {
		$this->productCode = $productCode;
		$this->apiParas["product_code"] = $productCode;
	}

	public function getProductCode() {
		return $this->productCode;
	}

	public function setRestoreTime($restoreTime) {
		$this->restoreTime = $restoreTime;
		$this->apiParas["restore_time"] = $restoreTime;
	}

	public function getRestoreTime() {
		return $this->restoreTime;
	}

	public function setPayAmountType($payAmountType) {
		$this->payAmountType = $payAmountType;
		$this->apiParas["pay_amount_type"] = $payAmountType;
	}

	public function getPayAmountType() {
		return $this->payAmountType;
	}

	public function setPayAmount($payAmount) {
		$this->payAmount = $payAmount;
		$this->apiParas["pay_amount"] = $payAmount;
	}

	public function getPayAmount() {
		return $this->payAmount;
	}
	
	public function setRestoreShopName($restoreShopName) {
		$this->restoreShopName = $restoreShopName;
		$this->apiParas["restore_shop_name"] = $restoreShopName;
	}
	
	public function getRestoreShopName() {
		return $this->restoreShopName;
	}
	*/

	public function getApiMethodName()
	{
		return "zhima.merchant.order.rent.complete";
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
