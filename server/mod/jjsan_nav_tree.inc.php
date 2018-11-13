<?php
$jjsan_nav_tree = [
	'admin'        => [
		'text' => '管理员中心',
		'sub_nav' => [
            'role' => [
                'opt' => '角色管理',
                'do' => [
                    'add' => '添加角色',
                    'edit' => '编辑角色'
                ]
            ],
            'user_manage' => [
                'opt' => '系统用户管理',
                'do' => [
                    'pass' => '通过',
                    'refuse' => '拒绝',
                    'search' => '搜索',
                    'delete' => '删除',
                    'lock'   => '锁定账户',
                    'unlock' => '解锁账户',
                    'resume' => '恢复账户',
                    'edit'   => '编辑用户',
                ]
            ],
            'install_man_manage' => [
                'opt' => '维护人员管理',
                'do'  => [
                    'add' => '添加维护人员',
                    'pass' => '通过审核',
                    'delete' => '删除角色',
                    'set_common' => '普通',
                    'set_install' => '维护',
                ]
            ],
            'access_verify' => [
                'opt' => '区域权限审核',
                'do' => [
                    'pass' => '通过',
                    'search' => '搜索',
                ]
            ],
            'shop_access_verify' => [
                'opt' => '商铺权限审核',
                'do' => [
                    'pass' => '通过',
                    'search' => '搜索',
                ]
            ],
            'access_apply' => [
                'opt' => '权限申请',
                'do' => [
                    'city_apply' => '申请城市',
                    'city_modify' => '修改申请',
                    'city_delete' => '删除城市权限',
                    'shop_apply' => '申请商铺',
                    'shop_delete' => '删除商铺权限',
                ]
            ],
            'pwd' => [
                'opt' => '修改密码'
            ],
		]
	],
    'settings'     => [
        'text' => '全局设置',
        'sub_nav' =>[
            'fee_settings' => [
                'opt' => '全局收费策略',
                'do'  => [
                    'strategy' => '调整收费策略',
                ]
            ],
            'local_fee_settings' => [
                'opt' => '局部收费策略',
                'do'  => [
                    'add'  => '添加策略',
                    'edit' => '编辑策略',
                    'delete' => '删除策略',
                ],
            ],
            'system_settings' => [
                'opt' => '全局同步策略',
                'do'  => [
                    'set' => '配置同步参数',
                ]
            ],
            'station_settings_strategy' => [
                'opt' => '局部同步策略',
                'do'  => [
                    'add' => '添加策略',
                    'edit' => '编辑策略',
                    'delete' => '删除策略',
                ]

            ],
            'wechat_settings' => [
                'opt' => '微信配置'
            ],
            'wechat_pictext' => [
                'opt' => '扫码推送配置',
                'do'  => [
                    'add'  => '添加配置',
                    'edit' => '编辑配置',
                    'delete' => '删除配置',
                ],
            ],
            'global_settings' => [
                'opt' => '客服电话配置'
            ],
        ]
    ],
    'item'         => [
        'text' => '商品管理',
        'sub_nav' =>[
            'list' => [
                'opt' => '商品列表',
                'do'  => [
                    'add' => '添加商品',
                    'edit' => '编辑商品',
                ]
            ]
        ]
    ],
    'shop'         => [
        'text' => '商铺管理',
        'sub_nav' => [
            'list' => [
                'opt' => '商铺列表',
                'do' => [
                    'edit_shop_type' => '编辑商铺',
                    'update_shop_picture' => '更新商铺图片'
                ]
            ],
            'add' => [
                'opt' => '添加商铺'
            ],
            'shop_type_list' => [
                'opt' => '商铺类型列表'
            ],
            'add_shop_type' => [
                'opt' => '添加商铺类型'
            ]
        ]
    ],
    'shop_station' => [
        'text' => '商铺站点管理',
        'sub_nav' =>[
            'list' => [
                'opt' => '商铺站点列表',
                'do' => [
                    'bind' => '绑定商铺',
                    'unbind' => '解绑商铺',
                    'setting_strategy' => '设置策略',
                    'shop_station_remove' => '撤机',
                    'shop_station_replace' => '换机',
                    'shop_station_go_up' => '上机',
                ],
            ],

        ]
    ],
    'station'      => [
        'text' => '站点管理',
        'sub_nav' =>[
            'list' => [
                'opt' => '站点状态列表',
                'do' => [
                    'unlockDevice' => '开锁',
                    'setting_strategy' => '设置策略',
                    'slot_action' => '槽位操作',
                    'manually_control' => '人工控制操作',
                    'query' => '查询信息',
                    'slotLock' => '上锁',
                    'slotUnlock' => '解锁',
                    'lend' => '人工借出',
                    'sync_umbrella' => '同步雨伞信息',
                    'reboot' => '人工重启',
                    'module_num' => '模组数量',
                    'upgrade' => '设备升级',
                    'init_set' => '初始化设备',
                    'element_module_open' => '开启模组功能',
                    'element_module_close' => '关闭模组功能',
                    'voice_module_open' => '开启语音功能',
                    'voice_module_close' => '关闭语音功能',
                    'show_mac' => '显示mac',
                    'show_qrcode' => '二维码展示',
                    'export' => '导出',
                ]
            ],
            'region_settings' => [
                'opt' => '区域设置'
            ],
            'heartbeat_log' => [
                'opt' => '心跳日志'
            ],
            'station_log' => [
                'opt' => '站点统计日志'
            ],
            'batch_import' => [
                'opt' => '批量导入'
            ],
            'umbrella_export' => [
                'opt' => '导出雨伞'
            ],
            'umbrella_export_2' => [
                'opt' => '导出雨伞2'
            ],
        ]
    ],
    'order'        => [
        'text' => '订单管理',
        'sub_nav' =>[
            'list' => [
                'opt' => '订单列表',
                'do' => [
                    'return_deposit' => '退押金',
                    'order_detail' => '订单详情',
                    'buyer_detail' => '用户信息',
                    'lost_order_finish' => '雨伞遗失',
                ]
            ],
        ]
    ],
    'user'         => [
        'text' => '用户管理',
        'sub_nav' =>[
            'list' => [
                'opt' => '用户列表',
                'do' => [
                    'search' => '搜索',
                ]
            ],
            'refund_list' => [
                'opt' => '提现列表'
            ],
            'zero_fee_user_list' => [
                'opt' => '零收费人员列表',
                'do' => [
                    'add' => '添加',
                    'delete' => '删除',
                ]
            ],
        ]
    ],
    'data'         => [
        'text' => '经营数据',
        'sub_nav' =>[
            'order_analysis' => [
                'opt' => '商户订单分析'
            ],
            'user_data_count' => [
                'opt' => '总用户统计',
                'do' => [
                    'search' => '搜索',
                ]
            ],
            'new_user_list' => [
                'opt' => '新用户统计',
                'do' => [
                    'search' => '搜索',
                ]
            ],
            'old_user_list' => [
                'opt' => '老用户统计',
                'do' => [
                    'search' => '搜索',
                ]
            ],
        ]
    ],
];
