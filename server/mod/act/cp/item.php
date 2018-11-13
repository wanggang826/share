<?php
use model\Api;

$menu = ct('menu');

switch ($opt) {

	case 'list':
	    if (isset($do)) {
	        switch ($do) {
                case 'edit':
                    if ($_POST) {
                        $data['subject'] = $subject;
                        $data['price'] = $price;
                        $data['desc'] = $desc;
                        if ($menu->update($id, $data)) {
                            Api::output([], 0, '编辑成功');
                        } else{
                            Api::output([], 1, '编辑失败');
                        }
                        exit;
                    }
                    $good = $menu->fetch($item_id);
                    include template("jjsan:cp/item/edit");
                    break;

                case 'add':
                    if ($_POST) {
                        $data['subject'] = $subject;
                        $data['price'] = $price;
                        $data['desc'] = $desc;
                        if ($menu->insert($data)) {
                            Api::output([], 0, '添加成功');
                        } else{
                            Api::output([], 1, '添加失败');
                        }
                        exit;
                    }
                    include template("jjsan:cp/item/add");
                    break;
            }
            exit;
        }
		$goods = $menu->get();
		break;

	default:
}
