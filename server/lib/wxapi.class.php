<?php
require_once "scurl.class.php";

class wxAPI
{
    public static $wxMsgData = [];

    public static $replyTpl
        = [
            'text'         => "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[%s]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					<FuncFlag>0</FuncFlag>
					</xml>",
            'pictext'      => "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[%s]]></MsgType>
					<ArticleCount>%d</ArticleCount>
					<Articles>%s</Articles>
					</xml>",
            'pictext_item' => "<item>
					<Title><![CDATA[%s]]></Title>
					<Description><![CDATA[%s]]></Description>
					<PicUrl><![CDATA[%s]]></PicUrl>
					<Url><![CDATA[%s]]></Url>
					</item>",
        ];

    public static function valid($isFirstTime = false)
    {
        $echoStr = $_GET["echostr"];

        //valid signature , option
        if (self::checkSignature()) {
            if ($isFirstTime) {
                echo $echoStr;
                exit;
            } else {
                return true;
            }
        }
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce     = $_GET["nonce"];

        $token  = TOKEN;
        $tmpArr = [$token, $timestamp, $nonce];
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    public static function getMsg()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"] ?: file_get_contents("php://input");

        if (!empty($postStr)) {
            libxml_disable_entity_loader(true);
            $postObj         = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            self::$wxMsgData = [
                'type'       => $postObj->MsgType,
                'client'     => $postObj->FromUserName,
                'me'         => $postObj->ToUserName,
                'createtime' => $postObj->CreateTime,
                'msgid'      => $postObj->MsgId,
            ];

            switch (self::$wxMsgData['type']) {
                case 'text':
                    self::$wxMsgData['content'] = $postObj->Content;
                    break;

                case 'image':
                    self::$wxMsgData['picurl']  = $postObj->PicUrl;
                    self::$wxMsgData['mediaid'] = $postObj->MediaId;
                    break;

                case 'event':
                    self::$wxMsgData['event'] = $postObj->Event;

                    switch (self::$wxMsgData['event']) {
                        case 'subscribe':
                        case 'SCAN':
                        case 'CLICK':
                        case 'VIEW':
                            self::$wxMsgData['eventkey'] = $postObj->EventKey ? $postObj->EventKey : null;
                            self::$wxMsgData['ticket']   = $postObj->Ticket ? $postObj->Ticket : null;
                            break;

                        case 'unsubscribe':
                            # code...
                            break;

                        case 'LOCATION':
                            self::$wxMsgData['latitude']  = $postObj->Latitude ? $postObj->Latitude : null;
                            self::$wxMsgData['longitude'] = $postObj->Longitude ? $postObj->Longitude : null;
                            self::$wxMsgData['precision'] = $postObj->Precision ? $postObj->Precision : null;
                            break;
                        case 'scancode_push':
                        case 'scancode_waitmsg':
                            self::$wxMsgData['eventkey']   = $postObj->EventKey ? $postObj->EventKey : null;
                            self::$wxMsgData['ScanType']   = $postObj->ScanCodeInfo->ScanType ? $postObj->ScanCodeInfo->ScanType : null;
                            self::$wxMsgData['ScanResult'] = $postObj->ScanCodeInfo->ScanResult ? $postObj->ScanCodeInfo->ScanResult : null;
                            break;
                        case 'TEMPLATESENDJOBFINISH':
                            self::$wxMsgData['status'] = $postObj->Status;
                            self::$wxMsgData['msgid']  = $postObj->MsgID;
                        default:
                            # code...
                            break;
                    }

                    break;

                default:
                    self::$wxMsgData['type'] = 'unknown';
                    break;
            }
        } else {
            return ['status' => 1, 'msg' => 'ERR_0001:CANT GET ANY POST DATA!'];
        }
    }

    public static function replyTextMsg($replyMsg)
    {
        $replyMsg  = $replyMsg ?: 'Nice to meet you, What can I do for you? - [Bacysoft Studio & LYStrong]';
        $resultStr = sprintf(self::$replyTpl['text'], self::$wxMsgData['client'], self::$wxMsgData['me'], time(), 'text', $replyMsg);
        echo $resultStr;
    }

    public static function replyPicTextMsg($replyMsg)
    {
        foreach ($replyMsg as $item) {
            $items[] = sprintf(self::$replyTpl['pictext_item'], $item['title'], $item['description'], $item['picurl'], $item['url']);
        }

        if ($num = count($items)) {
            $resultStr = sprintf(self::$replyTpl['pictext'], self::$wxMsgData['client'], self::$wxMsgData['me'], time(), 'news', $num, implode('', $items));
            echo $resultStr;
        } else {
            echo "success";
            exit;
        }
    }

    public static function updateAccessToken()
    {
        $api = "https://api.weixin.qq.com/cgi-bin/token";

        $data = [
            'grant_type' => 'client_credential',
            'appid'      => AppID,
            'secret'     => AppSecret,
        ];

        $wxcurl = new sCurl($api, 'GET', $data);

        $ret              = json_decode($wxcurl->sendRequest(), 1);
        $ret['timestamp'] = time();

        return $ret;
    }

    public static function createQrcode($wxAccessToken, $qid, $type = 'LIMIT', $exptime = 604800)
    {
        $api = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" . $wxAccessToken;

        if ($type == 'LIMIT') {
            $data = "{\"action_name\": \"QR_LIMIT_SCENE\", \"action_info\": {\"scene\": {\"scene_id\": $qid}}}";
        } else {
            $data = "{\"expire_seconds\": $exptime,\"action_name\": \"QR_SCENE\", \"action_info\": {\"scene\": {\"scene_id\": $qid}}}";
        }

        $wxcurl = new sCurl($api, 'POST', $data);

        return json_decode($wxcurl->sendRequest(), 1);
    }

    public static function getQrcode($ticket)
    {
        $api = "https://mp.weixin.qq.com/cgi-bin/showqrcode";

        $data = [
            'ticket' => $ticket,
        ];

        $wxcurl = new sCurl($api, 'GET', $data);

        return $wxcurl->sendRequest();
    }

    public static function getSignPackage($jsapiTicket, $url)
    {
        $timestamp = time();
        $nonceStr  = self::createNonceStr();

        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = [
            "appId"     => AppID,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            //"url"       => $url,
            "signature" => $signature,
            //"rawString" => $string
        ];
        return $signPackage;
    }

    public static function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public static function updateJsApiTicket($wxAccessToken)
    {
        $api = "https://api.weixin.qq.com/cgi-bin/ticket/getticket";

        $data = [
            'type'         => 'jsapi',
            'access_token' => $wxAccessToken,
        ];

        $wxcurl = new sCurl($api, 'GET', $data);

        $ret              = json_decode($wxcurl->sendRequest(), 1);
        $ret['timestamp'] = time();

        return $ret;
    }

    public static function getUserInfo($wxAccessToken, $openid)
    {
        $api = "https://api.weixin.qq.com/cgi-bin/user/info";

        $data = [
            'access_token' => $wxAccessToken,
            'openid'       => "$openid",
            'lang'         => 'zh_CN',
        ];

        $wxcurl = new sCurl($api, 'GET', $data);

        return json_decode($wxcurl->sendRequest(), 1);
    }

    public static function getUserInfoBatch($wxAccessToken, $openids)
    {
        $api = "https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token=" . $wxAccessToken;

        $data = [];
        foreach ($openids as $id) {
            $data['user_list'][] = ['openid' => $id, 'lang' => 'zh-CN'];
        }

        $wxcurl = new sCurl($api, 'POST', json_encode($data));
        return json_decode($wxcurl->sendRequest(), 1);
    }

    public static function getMaterial($wxAccessToken, $type, $offset, $count)
    {
        $api = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=" . $wxAccessToken;

        $data = [
            'type'   => $type,
            'offset' => $offset,
            'count'  => $count,
        ];

        $wxcurl = new sCurl($api, 'POST', json_encode($data));
        return json_decode($wxcurl->sendRequest(), 1);
    }

    public static function getAuthorizedAccessTokenInPage($code)
    {
        $api = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . AppID . "&secret=" . AppSecret . "&code=$code&grant_type=authorization_code";

        $wxcurl = new sCurl($api, 'GET', null);
        return json_decode($wxcurl->sendRequest(), 1);
    }

    public static function sendTemplateMsg($wxAccessToken, $data)
    {
        $api    = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $wxAccessToken;
        $wxcurl = new sCurl($api, 'POST', json_encode($data), [CURLOPT_TIMEOUT => 5]); // timeout 5s
        return json_decode($wxcurl->sendRequest(), 1);
    }

    public static function setGroupForUser($wxAccessToken, $openid, $groupid)
    {
        $api    = "https://api.weixin.qq.com/cgi-bin/groups/members/update?access_token=" . $wxAccessToken;
        $data   = ["openid" => $openid, "to_groupid" => $groupid];
        $wxcurl = new sCurl($api, 'POST', json_encode($data));
        return json_decode($wxcurl->sendRequest(), 1);
    }

    public static function batchSetGroupForUser($wxAccessToken, $openidList, $groupid)
    {
        $api = "https://api.weixin.qq.com/cgi-bin/groups/members/batchupdate?access_token=" . $wxAccessToken;
        // $openidList size不能超过50
        $data   = ["openid_list" => $openidList, "to_groupid" => $groupid];
        $wxcurl = new sCurl($api, 'POST', json_encode($data));
        return json_decode($wxcurl->sendRequest(), 1);
    }

    public static function weappGetSessionKey($weappId, $weappSecret, $code)
    {
        $api    = "https://api.weixin.qq.com/sns/jscode2session?appid=$weappId&secret=$weappSecret&js_code=$code&grant_type=authorization_code";
        $wxcurl = new sCurl($api, 'GET', null);
        return json_decode($wxcurl->sendRequest(), 1);

    }

    public static function weappDecryptBizData($weappId, $sessionKey, $encryptedData, $iv)
    {
        require_once "wxBizDataCrypt/wxBizDataCrypt.php";
        $pc      = new WXBizDataCrypt($weappId, $sessionKey);
        $errCode = $pc->decryptData($encryptedData, $iv, $data);

        if ($errCode == 0) {
            return json_decode($data, true);
        } else {
            return $errCode;
        }
    }

}
