<?php

require_once "scurl.class.php";

class weAPI
{

    protected static $key_weapp_access_token = 'jjsan_weapp_access_token';

    private static function _updateAccessToken()
    {
        $api = "https://api.weixin.qq.com/cgi-bin/token";

        $data = [
            'grant_type' => 'client_credential',
            'appid'      => WEAPP_APP_ID,
            'secret'     => WEAPP_APP_SECRET,
        ];

        $wecurl = new sCurl($api, 'GET', $data);

        $ret               = json_decode($wecurl->sendRequest(), true);
        $ret['expires_in'] = time() + 7200 - 60; // 少60秒
        C::t("common_setting")->update(self::$key_weapp_access_token, $ret);
        return $ret['access_token'];
    }

    public static function updateAccessToken()
    {
        return self::_updateAccessToken();
    }

    private static function _getAccessToken()
    {
        $rst          = C::t("common_setting")->fetch(self::$key_weapp_access_token, true);
        if (empty($rst) || $rst['expires_in'] < time()) {
            return self::_updateAccessToken();
        } else {
            return $rst['access_token'];
        }
    }

    public static function sendWeTemplateMsg($data)
    {
        $wxAccessToken = self::_getAccessToken();
        $api           = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $wxAccessToken;
        $wecurl        = new sCurl($api, 'POST', json_encode($data), [CURLOPT_TIMEOUT => 5]); // timeout 5s
        return json_decode($wecurl->sendRequest(), 1);
    }

}
