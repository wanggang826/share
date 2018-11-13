<?php
require_once "aop/AopClient.php";

class AlipayAPI
{
    public static $msgData = [];
    private static $aop = null;
    private static $access_token = '';

    public static function initialize()
    {
        //支付宝使用了新的加密算法RSA2
        self::$aop = new AopClient(ALIPAY_APPID, ALIPAY_GATEWAY, ALIPAY_MERCHANT_PRIVATE_KEY_FILE, ALIPAY_MERCHANT_PUBLIC_KEY_FILE, ALIPAY_WINDOW_PUBLIC_KEY_FILE, '1.0', 'RSA2');
    }

    public static function getResponseNodeName($request)
    {
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        return $responseNode;
    }

    public static function valid($isVerifyGW)
    {
        if (empty ($_GET["sign"]) || empty ($_GET["sign_type"]) || empty ($_GET["biz_content"]) || empty ($_GET["service"]) || empty ($_GET["charset"])) {
            // 验证芝麻信用的通知
            if (empty($_GET['notify_type']) || empty($_GET["sign"]) || empty($_GET["sign_type"]) || empty($_GET["order_no"])) {
                echo "some parameter is empty.";
                LOG::DEBUG("some parameter is empty.");

                return false;
            }
        }
        // 先验证签名
        $signVerify = self::$aop->rsaCheckV2($_GET, ALIPAY_WINDOW_PUBLIC_KEY_FILE);
        if (!$signVerify) {
            // 如果验证网关时，请求参数签名失败，则按照标准格式返回，方便在服务窗后台查看。
            if ($isVerifyGW) {
                self::$aop->verifygw(false);
            } else {
                return false;
            }
        }

        // 验证网关请求
        if ($isVerifyGW) {
            self::$aop->verifygw(true);
        }

        return true;
    }

    public static function getMsg()
    {
        $biz_content = $_GET["biz_content"];

        if (empty($biz_content)) {
            return false;
        }

        self::$msgData = ['userinfo' => self::getNode($biz_content, "UserInfo"), 'client' => self::getNode($biz_content, "FromAlipayUserId"), 'me' => self::getNode($biz_content, "AppId"), 'createtime' => self::getNode($biz_content, "CreateTime"), 'type' => self::getNode($biz_content, "MsgType"), 'event' => self::getNode($biz_content, "EventType"),];

        switch (self::$msgData['type']) {
            case 'text':
                self::$msgData['content'] = self::getNode($biz_content, "Text");
                break;

            case 'image':
                self::$msgData['mediaid'] = self::getNode($biz_content, "MediaId");
                self::$msgData['format'] = self::getNode($biz_content, "Format");
                break;

            case 'event':
                switch (self::$msgData['event']) {
                    case 'follow':
                    case 'enter':
                        // 二维码进入
                        $actionParam = self::getNode($biz_content, "ActionParam");
                        $arr = json_decode($actionParam);
                        $sceneId = $arr->scene->sceneId;
                        self::$msgData['eventkey'] = $sceneId;
                        break;
                    case 'click':
                        self::$msgData['eventkey'] = self::getNode($biz_content, "ActionParam");
                        break;

                    case 'unfollow':
                        # code...
                        break;
                    default:
                        # code...
                        break;
                }

                break;

            default:
                self::$msgData['type'] = 'unknown';
                break;
        }

        return true;
    }

    /**
     * 直接获取xml中某个结点的内容
     *
     * @param unknown $xml
     * @param unknown $node
     */
    private static function getNode($xml, $node)
    {
        $xml = "<?xml version=\"1.0\" encoding=\"GBK\"?>" . $xml;
        $dom = new DOMDocument ("1.0", "GBK");
        $dom->loadXML($xml);
        $event_type = $dom->getElementsByTagName($node);

        return $event_type->item(0)->nodeValue;
    }

    public static function replyTextMsg($replyMsg, $chat = 0)
    {
        $replyMsg = $replyMsg ?: 'Nice to meet you, What can I do for you?';

        $biz_content = ['to_user_id' => self::$msgData['client'], 'msg_type' => 'text', 'text' => ['content' => $replyMsg], 'chat' => $chat,];
        $biz_content = self::$aop->JSON($biz_content);
        require_once "aop/request/AlipayOpenPublicMessageCustomSendRequest.php";
        $custom_send = new AlipayOpenPublicMessageCustomSendRequest ();
        $custom_send->setBizContent($biz_content);
        self::$aop->execute($custom_send);
    }

    public static function replyPicTextMsg($replyMsg, $chat = 0)
    {
        foreach ($replyMsg as $item) {
            $articles[] = ['title' => $item['title'], 'desc' => $item['description'], 'image_url' => $item['picurl'], 'url' => $item['url'], 'action_name' => $item['action_name']];
        }

        $biz_content = ['to_user_id' => self::$msgData['client'], 'msg_type' => 'image-text', 'articles' => $articles, 'chat' => $chat,];
        $biz_content = self::$aop->JSON($biz_content);
        require_once "aop/request/AlipayOpenPublicMessageCustomSendRequest.php";
        $custom_send = new AlipayOpenPublicMessageCustomSendRequest ();
        $custom_send->setBizContent($biz_content);
        self::$aop->execute($custom_send);
    }

    public static function createMenu($biz)
    {
        $biz = json_encode($biz);

        require_once "aop/request/AlipayOpenPublicMenuCreateRequest.php";
        $request = new AlipayOpenPublicMenuCreateRequest ();
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->alipay_open_public_menu_create_response->msg;
    }

    public static function updateMenu($biz)
    {
        $biz = json_encode($biz);

        require_once "aop/request/AlipayOpenPublicMenuModifyRequest.php";
        $request = new AlipayOpenPublicMenuModifyRequest ();
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->alipay_open_public_menu_modify_response->msg;
    }

    public static function createQrcode($sceneId, $exptime = 1800)
    {
        $qrBiz = [
            'code_info' => ['scene' => ['scene_id' => $sceneId]],
            'expire_second' => $exptime,
            'show_logo' => 'Y',
        ];
        $qrBiz = json_encode($qrBiz);

        require_once "aop/request/AlipayOpenPublicQrcodeCreateRequest.php";
        $request = new AlipayOpenPublicQrcodeCreateRequest ();
        $request->setBizContent($qrBiz);
        $result = self::$aop->execute($request);

        if ($result->alipay_open_public_qrcode_create_response->code == 10000) {
            return $result->alipay_open_public_qrcode_create_response->code_img;
        }

        return false;
    }


    public static function createQrcodeUnLimit($sceneId)
    {
        $qrBiz = [
            'code_info' => ['scene' => ['scene_id' => "$sceneId"]],
            'code_type' => 'PERM',
            'show_logo' => 'Y',
        ];
        $qrBiz = json_encode($qrBiz);

        require_once "aop/request/AlipayOpenPublicQrcodeCreateRequest.php";
        $request = new AlipayOpenPublicQrcodeCreateRequest ();
        $request->setBizContent($qrBiz);
        $result = self::$aop->execute($request);

        if ($result->alipay_open_public_qrcode_create_response->code == 10000) {
            return $result->alipay_open_public_qrcode_create_response->code_img;
        }

        return false;
    }

    public static function getOpenid($scope = "auth_base")
    {
        $result = AlipayAPI::getOAuthToken($scope);

        return empty($result) ? null : $result->user_id;
    }

    /**
     * url : https://doc.open.alipay.com/docs/doc.htm?treeId=289&articleId=105656&docType=1
     * @param string $scope
     * auth_base：以auth_base为scope发起的网页授权，是用来获取进入页面的用户的userId的，
     * 并且是静默授权并自动跳转到回调页的。用户感知的就是直接进入了回调页（通常是业务页面）。
     * auth_user：以auth_userinfo为scope发起的网页授权，是用来获取用户的基本信息的（比如头像、昵称等）。
     * 这种授权需要用户手动同意，用户同意后，就可在授权后获取到该用户的基本信息。
     * auth_zhima: 以auth_zhima为scope发起的网页授权，是用来获取用户的芝麻信用评分及相关信用信息。
     * 这种授权需要用户手动同意，用户同意后，就可在授权后获取到该用户的基本信息。
     * auth_ecard: 以auth_ecard为scope发起的网页授权，应用于商户会员卡开卡接口用户授权。
     * 这种授权需要用户手动同意，用户同意后，商户就可在授权后帮助用户开通会员卡。
     * @return null
     */


    public static function getOAuthToken($scope = "auth_base")
    {
        if (!isset($_GET['auth_code'])) {
            //触发支付宝返回code码
            $baseUrl = urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=" . ALIPAY_APPID . "&scope=" . $scope . "&redirect_uri=" . $baseUrl;
            Header("Location: $url");
            exit;
        } else {
            //获取auth_code码，以获取openid
            $auth_code = $_GET['auth_code'];
            require_once __DIR__ . "/aop/request/AlipaySystemOauthTokenRequest.php";
            $request = new AlipaySystemOauthTokenRequest();
            $request->setCode($auth_code);
            $request->setGrantType("authorization_code");
            $result = self::$aop->execute($request);

            if (!isset($result->alipay_system_oauth_token_response)) {
                LOG::ERROR('alipay get auth user openid error: ' . var_export($result, true));

                return null;
            }

            return $result->alipay_system_oauth_token_response;
        }
    }

    public static function getOpenidFromMp($code) {
        //获取auth_code码，以获取openid
        require_once __DIR__ . "/aop/request/AlipaySystemOauthTokenRequest.php";
        $request = new AlipaySystemOauthTokenRequest();
        $request->setCode($code);
        $request->setGrantType("authorization_code");
        $result = self::$aop->execute($request);
        LOG::DEBUG('alipay.system.oauth.token response is : ' . var_export($result, 1));
        if (!isset($result->alipay_system_oauth_token_response->user_id)) {
            return false;
        }
        self::$access_token = $result->alipay_system_oauth_token_response->access_token;
        return $result->alipay_system_oauth_token_response->user_id;
    }

    // 这个方法要用到getOpenidFromMp方法，不然access_token为空
    public static function getUserInfoAfterGetOpenid()
    {
        require_once __DIR__ . "/aop/request/AlipayUserInfoShareRequest.php";
        $request = new AlipayUserInfoShareRequest();
        $userInfo = self::$aop->execute($request, self::$access_token);
        if (!isset($userInfo->alipay_user_info_share_response)) {
            LOG::ERROR('alipay get auth user info error: ' . var_export($userInfo, true));
            return false;
        }

        return $userInfo->alipay_user_info_share_response;
    }

    public static function getUserInfo()
    {

        $result = AlipayAPI::getOAuthToken("auth_user");
        require_once __DIR__ . "/aop/request/AlipayUserInfoShareRequest.php";
        $request = new AlipayUserInfoShareRequest();
        $userInfo = self::$aop->execute($request, $result->access_token);
        if (!isset($userInfo->alipay_user_info_share_response)) {
            LOG::ERROR('alipay get auth user info error: ' . var_export($userInfo, true));
            return null;
        }
        return $userInfo->alipay_user_info_share_response;
    }

    public static function sendTemplateMsg($biz)
    {
        $biz = json_encode($biz);

        //https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.ucODFF&treeId=53&articleId=103463&docType=1
        require_once "aop/request/AlipayOpenPublicMessageSingleSendRequest.php";
        $request = new AlipayOpenPublicMessageSingleSendRequest();
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        if ($result->alipay_open_public_message_single_send_response->code != 10000) {
            LOG::ERROR('alipay send template msg error: ' . var_export($result, true));
            return false;
        }

        return true;
    }

    public function getPrivateKeyStr($pub_pem_path)
    {
        $content = file_get_contents($pub_pem_path);
        $content = str_replace("-----BEGIN RSA PRIVATE KEY-----", "", $content);
        $content = str_replace("-----END RSA PRIVATE KEY-----", "", $content);
        $content = str_replace("\r", "", $content);
        $content = str_replace("\n", "", $content);

        return $content;
    }

    public static function refund($biz)
    {
        $biz = json_encode($biz);

        //https://doc.open.alipay.com/docs/api.htm?spm=a219a.7386797.0.0.WOZM8z&docType=4&apiId=759
        require_once "aop/request/AlipayTradeRefundRequest.php";
        $request = new AlipayTradeRefundRequest();
        $request->setBizContent($biz);
        $result = self::$aop->execute($request);

        return $result->alipay_trade_refund_response;
    }

    public static function buildZhimaRentOrderSubmitForm($biz)
    {
        $requestUrl = self::getZhimaRentOrderUrl($biz);
        $sHtml = "<form id='zhimasubmit' name='zhimasubmit' action='" . $requestUrl . "' method='post'>";
        $sHtml = $sHtml . "<script>document.forms['zhimasubmit'].submit();</script>";
        return $sHtml;
    }

    public static function getZhimaRentOrderUrl($biz)
    {
        $biz = self::$aop->JSON($biz);

        require_once "aop/request/ZhimaMerchantOrderRentCreateRequest.php";
        $request = new ZhimaMerchantOrderRentCreateRequest();
        $request->setBizContent($biz);
        $requestUrl = self::$aop->execute($request, null, null, true);
        return $requestUrl;
    }

    public static function zhimaOrderRentComplete($biz)
    {
        $biz = json_encode($biz);

        require_once "aop/request/ZhimaMerchantOrderRentCompleteRequest.php";
        $request = new ZhimaMerchantOrderRentCompleteRequest();
        $request->setBizContent($biz);
        $resp = self::$aop->execute($request);

        return $resp->zhima_merchant_order_rent_complete_response;
    }

    public static function zhimaOrderRentQuery($biz)
    {
        $biz = self::$aop->JSON($biz);

        require_once "aop/request/ZhimaMerchantOrderRentQueryRequest.php";
        $request = new ZhimaMerchantOrderRentQueryRequest();
        $request->setBizContent($biz);
        $resp = self::$aop->execute($request);

        return $resp->zhima_merchant_order_rent_query_response;
    }

    public static function zhimaBorrowEntityUpload($biz)
    {
        $biz = self::$aop->JSON($biz);

        require_once "aop/request/ZhimaMerchantBorrowEntityUploadRequest.php";
        $request = new ZhimaMerchantBorrowEntityUploadRequest();
        $request->setBizContent($biz);
        $resp = self::$aop->execute($request);

        return $resp->zhima_merchant_borrow_entity_upload_response;
    }

    public static function AlipayDataDataserviceBillDownloadurlQuery($bill_date)
    {
        $requestDataArray = ['bill_type' => 'trade', 'bill_date' => $bill_date];
        $biz = self::$aop->JSON($requestDataArray);
        require_once "aop/request/AlipayDataDataserviceBillDownloadurlQueryRequest.php";
        $request = new AlipayDataDataserviceBillDownloadurlQueryRequest();
        $request->setBizContent($biz);
        $resp = self::$aop->execute($request);
        return $resp->{self::getResponseNodeName($request)};
    }

    // 手机网站支付2.0版本

    public static function zhimaOrderRentCancel($biz)
    {
        $biz = self::$aop->JSON($biz);

        require_once "aop/request/ZhimaMerchantOrderRentCancelRequest.php";
        $request = new ZhimaMerchantOrderRentCancelRequest();
        $request->setBizContent($biz);
        $resp = self::$aop->execute($request);

        return $resp->zhima_merchant_order_rent_cancel_response;
    }

    //================= Zhimaxinyong =====================//

    public function mkAckMsg()
    {
        $response_xml = "<XML><ToUserId><![CDATA[" . self::$msgData['client'] . "]]></ToUserId><AppId><![CDATA[" . ALIPAY_APPID . "]]></AppId><CreateTime>" . time() . "</CreateTime><MsgType><![CDATA[ack]]></MsgType></XML>";
        $return_xml = self::$aop->signResponse($response_xml, "UTF-8", ALIPAY_MERCHANT_PRIVATE_KEY_FILE);

        return $return_xml;
    }

    public static function verifyPayNotifyV2()
    {
        $alipayPublicKey = self::getPublicKeyStr(ALIPAY_WINDOW_PUBLIC_KEY_FILE);
        require_once __DIR__ . '/pay/lib/alipay_notify.class.php';
        $alipayConfig = [
            'sign_type' => $_GET['sign_type'],
            'alipay_public_key' => $alipayPublicKey,
        ];
        $alipayNotify = new AlipayNotify($alipayConfig);

        return $alipayNotify->verifyNotify();
    }

    public function getPublicKeyStr($pub_pem_path)
    {
        $content = file_get_contents($pub_pem_path);
        $content = str_replace("-----BEGIN PUBLIC KEY-----", "", $content);
        $content = str_replace("-----END PUBLIC KEY-----", "", $content);
        $content = str_replace("\r", "", $content);
        $content = str_replace("\n", "", $content);

        return $content;
    }

    public static function buildAlipaySubmitFormV2(array $requestParams)
    {
        require_once 'aop/request/AlipayTradeWapPayRequest.php';
        $request = new AlipayTradeWapPayRequest();
        $request->setReturnUrl("http://" . SERVER_DOMAIN . $requestParams['return_url']);
        $request->setNotifyUrl("http://" . SERVER_DOMAIN . "/alipaynotify.php");
        $bizContentArray = [
            'body' => $requestParams['body'],
            'subject' => $requestParams['subject'],
            'out_trade_no' => $requestParams['out_trade_no'],
            'timeout_express' => $requestParams['timeout_express'],
            'total_amount' => $requestParams['total_amount'],
            'productCode' => 'QUICK_WAP_PAY', // 固定值
        ];
        $request->setBizContent(json_encode($bizContentArray, JSON_UNESCAPED_UNICODE));

        return self::$aop->pageExecute($request, "post");
    }
}

AlipayAPI::initialize();
