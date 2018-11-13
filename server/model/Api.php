<?php
namespace model;

class Api extends Model
{
    // 调用接口的URL
    public static $API_URL;

    const SUCCESS        = 0;
    const NO_MUST_PARAM  = 1;
    const API_NOT_EXISTS = -1;
    const OPERATION_FAIL = 2;
    const ERROR_UNKNOWN  = 404;
    const ERROR          = 9999;

    const CODE_INVALID           = 3;
    const ENCRYPTED_DATA_INVALID = 4;
    const SESSION_EXPIRED        = 5;
    const ERROR_QR_CODE          = 555;

    public static $msg
        = [
            self::SUCCESS         => '成功',
            self::NO_MUST_PARAM   => '缺少必要的参数',
            self::API_NOT_EXISTS  => '该api不存在',
            self::OPERATION_FAIL  => '操作失败',
            self::ERROR           => '接口调用失败',
            self::ERROR_UNKNOWN   => '未知错误',
            self::ERROR_QR_CODE   => '错误二维码',
            self::SESSION_EXPIRED => '状态过期', // session过期

        ];

    private static $logStr = [];

    public function __construct()
    {
        parent::__construct();
    }

    /*
        如果 第三个参数 有设置的化 直接 使用 $msg
        如果设置了 $msg 的话 ，返回说明 为 错误码对应的说明
        如果没有设置 $msg 或者 $msg['code'] 不存在 则使用 $msg
    */
    public static function output(array $data = [], $code = Api::SUCCESS, $m = '')
    {
        $str['data'] = $data;
        $str['code'] = $code;
        if (isset(self::$msg[$code])) {
            $str['msg'] = self::$msg[$code];
        }
        if ($m) {
            $str['msg'] = $m;
        }
        self::$logStr = $str;
        self::outputJSON($str);
    }

    public static function outputJsonp(array $data = [], $code = 0, $m = '')
    {
        $str['data'] = $data;
        $str['code'] = $code;
        if (isset(self::$msg[$code])) {
            $str['msg'] = self::$msg[$code];
        }
        if ($m) {
            $str['msg'] = $m;
        }
        self::$logStr = $str;
        header('Content-type: application/json');
        echo $_GET['callback'] . "(" . json_encode($str, true) . ")";
        // exit;
    }


    public static function fail($code, $m = '')
    {
        self::output([], $code, $m);
    }

    /*
    *	构造返回数组
    */
    public static function make($code = 0, array $data = [], $m = '')
    {
        $str['data'] = $data;
        $str['code'] = $code;
        if (isset(self::$msg[$code])) {
            $str['msg'] = self::$msg[$code];
        }
        if ($m) {
            $str['msg'] = $m;
        }
        self::$logStr = $str;
        return $str;
    }

    public static function outputJSON(array $data = [])
    {
        header('Content-type: application/json');
        echo json_encode($data);
    }

    public static function getLogStr()
    {
        return self::$logStr;
    }

    /*
        检查必须传的参数
        $con 传入的参数
        $must 参数列表
    */
    public static function check(array $con, array $must)
    {
        foreach ($must as $value) {
            if (!isset($con[$value])) {
                return false;
            }
        }
        return true;
    }

    /*
        从一个数组中挑选出自己想要的几个字段,这些字段不一定都能获取到，并且不能为空
    */
    public static function data_pick(array $a, array $b)
    {
        $temp = [];
        foreach ($b as $v) {
            if (isset($a[$v]) && !empty($a[$v])) {
                $temp[$v] = $a[$v];
            }
        }
        return $temp;
    }
}
