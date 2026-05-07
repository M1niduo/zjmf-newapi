<?php

// 加载自动加载器
require_once __DIR__ . '/logic/autoload.php';

use servers\newapi\logic\BaseLogic;
use servers\newapi\logic\InstanceLogic;
use servers\newapi\logic\ClientAreaLogic;

define('NEWAPI_DEBUG', false);

function newapi_MetaData()
{
    return [
        'DisplayName' => 'Newapi',
        'APIVersion'  => '20260227',
    ];
}

function newapi_ConfigOptions()
{
    return [
        'capacity' => [
            'type'        => 'text',
            'name'        => 'Token的quota',
            'description' => '',
            'default'     => '500000',
            'key'         => 'capacity',
        ],
        'model_limits_enabled' => [
            'type'        => 'dropdown',
            'name'        => '启用模型限制',
            'description' => '',
            'default'     => 'false',
            'key'         => 'model_limits_enabled',
            'options'     => ['true' => '启用', 'false' => '禁用'],
        ],
        'model_limits' => [
            'type'        => 'text',
            'name'        => '模型限制列表',
            'description' => '',
            'default'     => '',
            'key'         => 'model_limits',
        ],
        'unlimited_quota' => [
            'type'        => 'dropdown',
            'name'        => '无限流量',
            'description' => '',
            'default'     => 'false',
            'key'         => 'unlimited_quota',
            'options'     => ['true' => '启用', 'false' => '禁用'],
        ]
    ];
}
/**
 * 测试链接状态
 */
function newapi_TestLink($params)
{
    $baseLogic = new BaseLogic($params);
    return $baseLogic->testLink();
}
/**
 * 添加的tab页
 */
function newapi_ClientArea($params)
{
    $instanceLogic = new InstanceLogic($params);
    $clientAreaLogic = new ClientAreaLogic($params);
    $instanceLogic->sync();
    return $clientAreaLogic->getClientArea();
}
/**
 * 页面
 */
function newapi_ClientAreaOutput($params, $key)
{
    $clientAreaLogic = new ClientAreaLogic($params);
    return $clientAreaLogic->getClientAreaOutput($key);
}
/**
 * 新增
 */
function newapi_CreateAccount($params)
{
    $instanceLogic = new InstanceLogic($params);
    $result =  $instanceLogic->create();
    sleep(2);
    $instanceLogic->sync();
    return $result;
}
/**
 * 删除
 */
function newapi_TerminateAccount($params)
{
    $instanceLogic = new InstanceLogic($params);
    return $instanceLogic->terminate();
}
/**
 * 状态
 */
function newapi_Status($params)
{
    $instanceLogic = new InstanceLogic($params);
    return $instanceLogic->status();
}
/**
 * 同步
 */
function newapi_Sync($params)
{
    $instanceLogic = new InstanceLogic($params);
    return $instanceLogic->sync();
}
/**
 * 购买流量包
 */
function newapi_FlowPacketPaid($params)
{
    $instanceLogic = new InstanceLogic($params);
    return $instanceLogic->packet();
}
/**
 * 工单
 */
function newapi_CreateTicket($params)
{
    return newapi_Sync($params);
}
/**
 * 续费
 */
function newapi_Renew($params)
{
    $instanceLogic = new InstanceLogic($params);
    return $instanceLogic->renew();
}
/**
 * 暂停
 */
function newapi_SuspendAccount($params)
{
    $instanceLogic = new InstanceLogic($params);
    return $instanceLogic->suspend();
}
/**
 * 取消暂停
 */
function newapi_UnsuspendAccount($params)
{
    $instanceLogic = new InstanceLogic($params);
    return $instanceLogic->unsuspend();
}
