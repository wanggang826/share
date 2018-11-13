<?php
use model\Api;
use model\FileUpload;
use model\User;

LOG::DEBUG("common api input parameters are : " . print_r($_GET, 1));
if(ENV_DEV){
    header('Access-Control-Allow-Origin:*');
    header('Access-Control-Allow-Methods: POST, GET');
    header('Access-Control-Allow-Headers:x-requested-with,content-type');
}

if(isset($_GET['session'])){
    $user = new User();
    $uid = $user->checkWeappLogin($_GET['session']);
    if(!$uid) {
        Api::output([], Api::SESSION_EXPIRED);
        exit;
    }
}

switch($opt){
	case "change_baidu_map_to_tencent":
        $lng = $_GET['lng'];
        $lat = $_GET['lat'];
        $url = "http://mapi.map.qq.com/translate/?type=3&points=$lng,$lat&output=jsonp";
        echo file_get_contents($url);
        break;

	case "change_baidu_coordinates_to_gaode":
        require_once JJSAN_DIR_PATH . 'mod/user_check.inc.php';
		$data['key'] = GAODE_MAP_KEY_FOR_API;
		$data['locations'] = "$lng,$lat";
		$data['coordsys'] = "baidu";
		$data['output'] = "JSON";
		$api = "http://restapi.amap.com/v3/assistant/coordinate/convert";
		$scurl = new sCurl( $api, 'GET', $data );
		$ret = $scurl->sendRequest();
        LOG::DEBUG("the result of convertion is : " . print_r($ret, 1));
		echo $ret;
		break;

	case "user_location_log":
	    require_once JJSAN_DIR_PATH . 'mod/user_check.inc.php';
        $data['uid'] = $uid;
        $data['lng'] = $lng;
        $data['lat'] = $lat;
        $data['js_date'] = $date;
        $a = ct('user_location_log') -> insert($data,false,true);
        echo json_encode($a);
        break;

    // 接收文件上传接口
	case "upload_file":
		$files = FileUpload::img(UPLOAD_FILE_ROOT_DIR,'/data/attachment/forum');
		Api::output(['files'=>$files]);
        // 接收上传文件的话　需要用普通header 而不是json header
        header('Content-Type:text/html; charset=utf-8');
		break;

    case "upload_upgrade_file":
        if ($_FILES['upgrade_file']['name']) {
            if (!file_exists(MINI_DATA . $_FILES['upgrade_file']['name']) && !$_FILES['upgrade_file']['error']) {
                LOG::DEBUG("upload upgrade file:" . print_r($_FILES, true));
                move_uploaded_file($_FILES['upgrade_file']['tmp_name'], MINI_DATA . $_FILES['upgrade_file']['name']);
                Api::output([], 0, '文件上传成功');
            } else {
               Api::output([], 1, '文件上传失败,重名或其他原因');
            }
        }
        break;

	default:
		Api::output([],Api::API_NOT_EXISTS);
		break;

}
