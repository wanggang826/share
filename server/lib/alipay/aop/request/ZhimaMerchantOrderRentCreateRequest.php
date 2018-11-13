<?php
/**
 * ALIPAY API: alipay.databiz.core.user.level.get request
 *
 * @author auto create
 * @since 1.0, 2014-10-10 16:50:26
 */
class ZhimaMerchantOrderRentCreateRequest
{
	/** 
	 * 业务参数
	 **/
	/*
	private $invokeType;
	private $invokeReturnUrl;
	private $notifyUrl;
	private $invokeState;
	private $outOrderNo;
	private $productCode;
	private $goodsName;
	private $rentInfo;
	private $rentUnit;
	private $rentAmount;
	private $depositState;
	private $borrowCycle;
	private $borrowCycleUnit;
	private $borrowShopName;
	*/
	private $bizContent;

	private $apiParas = array();
	private $terminalType;
	private $terminalInfo;
	private $prodCode;
	private $apiVersion="1.0";
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
	public function setInvokeType($invokeType) {
		$this->invokeType = $invokeType;
		$this->apiParas["invoke_type"] = $invokeType;
	}

	public function getInvokeType() {
		return $this->invokeType;
	}

	public function setInvokeReturnUrl($invokeReturnUrl) {
		$this->invokeReturnUrl = $invokeReturnUrl;
		$this->apiParas["invoke_return_url"] = $invokeReturnUrl;
	}

	public function getInvokeReturnUrl() {
		return $this->invokeReturnUrl;
	}

	public function setNotifyUrl($notifyUrl) {
		$this->notifyUrl = $notifyUrl;
		$this->apiParas["notify_url"] = $notifyUrl;
	}

	public function getNotifyUrl() {
		return $this->notifyUrl;
	}

	public function setInvokeState($invokeState) {
		$this->invokeState = $invokeState;
		$this->apiParas["invoke_state"] = $invokeState;
	}

	public function getInvokeState() {
		return $this->invokeState;
	}

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

	public function setGoodsName($goodsName) {
		$this->goodsName = $goodsName;
		$this->apiParas["goods_name"] = $goodsName;
	}
	
	public function getGoodsName() {
		return $this->goodsName;
	}

	public function setRentInfo($rentInfo) {
		$this->rentInfo = $rentInfo;
		$this->apiParas["rent_info"] = $rentInfo;
	}
	
	public function getRentInfo() {
		return $this->rentInfo;
	}

	public function setRentUnit($rentUnit) {
		$this->rentUnit = $rentUnit;
		$this->apiParas["rent_unit"] = $rentUnit;
	}
	
	public function getRentUnit() {
		return $this->rentUnit;
	}

	public function setRentAmount($rentAmount) {
		$this->rentAmount = $rentAmount;
		$this->apiParas["rent_amount"] = $rentAmount;
	}
	
	public function getRentAmount() {
		return $this->rentAmount;
	}

	public function setDepositState($depositState) {
		$this->depositState = $depositState;
		$this->apiParas["deposit_state"] = $depositState;
	}
	
	public function getDepositState() {
		return $this->depositState;
	}

	public function setBorrowCycle($borrowCycle) {
		$this->borrowCycle = $borrowCycle;
		$this->apiParas["borrow_cycle"] = $borrowCycle;
	}
	
	public function getBorrowCycle() {
		return $this->borrowCycle;
	}

	public function setBorrowCycleUnit($borrowCycleUnit) {
		$this->borrowCycleUnit = $borrowCycleUnit;
		$this->apiParas["borrow_cycle_unit"] = $borrowCycleUnit;
	}
	
	public function getBorrowCycleUnit() {
		return $this->borrowCycleUnit;
	}

	public function setBorrowShopName($borrowShopName) {
		$this->borrowShopName = $borrowShopName;
		$this->apiParas["borrow_shop_name"] = $borrowShopName;
	}
	
	public function getBorrowShopName() {
		return $this->borrowShopName;
	}
	*/
	
	public function getApiMethodName()
	{
		return "zhima.merchant.order.rent.create";
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
