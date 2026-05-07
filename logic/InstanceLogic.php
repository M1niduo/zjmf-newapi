<?php

namespace servers\newapi\logic;

use \think\Db;

class InstanceLogic extends BaseLogic
{

    public function terminate()
    {
        $params = $this->params;
        $instance = $this->getInstance();
        if (!$instance) {
            return ['status' => 'error', 'msg' => '无法找到实例ID'];
        }

        $data = [
            'url'  => '/api/token/' . $instance['id'],
            'type' => 'application/json',
            'data' => [],
        ];
        $res = $this->curl($data, 'DELETE');

        return isset($res['success']) && $res['success'] == true
            ? ['status' => 'success', 'msg' => $res['message'] ?? '删除成功']
            : ['status' => 'error', 'msg' => $res['message'] ?? '删除失败'];
    }

    public function status()
    {
        $params = $this->params;

        $instance = $this->getInstance();
        if (!$instance) {
            return ['status' => 'error', 'msg' => '无法找到实例'];
        }

        $result = ['status' => 'success'];

        $containerStatus = $instance['status'];

        switch (strtoupper($containerStatus)) {
            case 1:
                $result['data']['status'] = 'on';
                $result['data']['des'] = '已启用';
                break;
            case 2:
                $result['data']['status'] = 'off';
                $result['data']['des'] = '已禁用';
                break;
            case 4:
                $result['data']['status'] = 'suspend';
                $result['data']['des'] = '额度已用尽-暂停';
                break;
            // 3 过期状态 - token 已过期
            default:
                $result['data']['status'] = 'unknown';
                $result['data']['des'] = '未知状态';
                break;
        }
        return $result;
    }

    public function sync()
    {
        $params = $this->params;

        $instance = $this->getInstance(true);

        if (!isset($instance)) {
            return ['status' => 'error', 'msg' => '无法找到实例'];
        }

        $update = [
            'dcimid' => $instance['id'],
            'dedicatedip'  => $params['server_ip'],
            'assignedips'  => $instance['key'],
            'domainstatus' => 'Active',
            'username'     => $instance['name'],
            'port' => $params['secure'] ? 443 : 80,
            'bwlimit' => $instance['unlimited_quota'] ? 0 : 1,
            'bwusage' =>  $instance['unlimited_quota'] ? 0.00 : (($total = $instance['used_quota'] + $instance['remain_quota']) ? $instance['used_quota'] / $total : 0.00),
        ];

        try {
            Db::name('host')->where('id', $params['hostid'])->update($update);
            return ['status' => 'success', 'msg' => '同步成功'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'msg' => '同步成功，但同步数据到面板失败: ' . $e->getMessage()];
        }

        return ['status' => 'error', 'msg' => '同步失败'];
    }

    public function suspend()
    {
        $params = $this->params;
        $instance = $this->getInstance(true);
        if (!$instance) {
            return ['status' => 'error', 'msg' => '无法找到实例'];
        }

        $data = [
            'url'  => '/api/token?status_only=true',
            'type' => 'application/json',
            'data' => [
                'id' => $instance['id'],
                'status' => 2
            ],
        ];
        $res = $this->curl($data, 'PUT');

        if (isset($res['success']) && $res['success'] == true) {
            return ['status' => 'success', 'msg' => $res['message'] ?? '实例暂停任务已提交'];
        } else {
            return ['status' => 'error', 'msg' => $res['message'] ?? '实例暂停失败'];
        }
    }

    public function unsuspend()
    {
        $params = $this->params;
        $this->debug('开始解除暂停实例', ['domain' => $params['domain']]);
        $params = $this->params;
        $instance = $this->getInstance(true);
        if (!$instance) {
            return ['status' => 'error', 'msg' => '无法找到实例'];
        }

        $data = [
            'url'  => '/api/token?status_only=true',
            'type' => 'application/json',
            'data' => [
                'id' => $instance['id'],
                'status' => 1
            ],
        ];
        $res = $this->curl($data, 'PUT');

        if (isset($res['success']) && $res['success'] == true) {
            return ['status' => 'success', 'msg' => $res['message'] ?? '实例恢复任务已提交'];
        } else {
            return ['status' => 'error', 'msg' => $res['message'] ?? '实例恢复失败'];
        }
    }
    /**
     * 解析传进来的参数，获取套餐的quota
     */
    public function configOptions()
    {
        $params = $this->params;
        $capacityMapping = ['1$' => 500000, '10$' => 5000000, '50$' => 25000000, '100$' => 50000000, '500$' => 250000000, '1000$' => 500000000];
        $params['configoptions']['capacity'] = (int) $params['configoptions']['capacity'] ?? $capacityMapping[$params['configoptions']['额度']];
        return $params['configoptions'];
    }

    public function create()
    {
        $params = $this->params;
        $this->debug('开始创建实例', ['domain' => $params['domain']]);
        $instance = $this->getInstance();

        if (isset($instance) && !empty($instance)) {
            $this->sync();
            return ['status' => 'error', 'msg' => $params['domain'] . '已开通! ID为：' . $instance['id']];
        }
        $configs = $this->configOptions();
        $data = [
            'url'  => '/api/token/',
            'type' => 'application/json',
            'data' => [
                'name' => $params['domain'],
                'expired_time' => 0,
                'group' => 'default',
                'cross_group_retry' => false,
                'allow_ips' => '',
                'remain_quota' => intval($configs['capacity']),
                'unlimited_quota' => $configs['unlimited_quota'] == 'false' ? false : true,
                'model_limits_enabled' => $configs['model_limits_enabled'] == 'false' ? false : true,
                'model_limits' => $configs['model_limits']
            ],
        ];
        if (isset($params['nextduedate']) && !empty($params['nextduedate'])) {
            $data['data']['expired_time'] = $params['nextduedate'];
        }
        $this->debug('发送创建请求', $data);
        $res = $this->curl($data, 'POST');

        $this->debug('创建响应', $res);

        if (!isset($res['success']) || $res['success'] != true) {
            return ['status' => 'error', 'msg' => '创建失败' . $res['message']];
        }

        return ['status' => 'success', 'msg' => $res['msg'] ?? '创建成功'];
    }

    public function renew()
    {
        $params = $this->params;
        $this->debug('开始续费', ['domain' => $params['domain'], 'nextduedate' => $params['nextduedate'] ?? null]);

        $instance = $this->getInstance();
        if (!$instance) {
            return ['status' => 'error', 'msg' => '无法找到实例'];
        }
        $configs = $this->configOptions();
        $updateData = array_merge($instance, [
            'remain_quota' => intval($instance['remain_quota']) + intval($configs['capacity']),
            'unlimited_quota' => $configs['unlimited_quota'] == 'false' ? false : true,
            'model_limits_enabled' => $configs['model_limits_enabled'] == 'false' ? false : true,
            'model_limits' => $configs['model_limits']
        ]);
        if (isset($params['nextduedate']) && !empty($params['nextduedate'])) {
            $updateData['expired_time'] = $params['nextduedate'];
        }
        // 是否允许无限流量
        // 是否允许模型
        $this->debug('续费更新数据', $updateData);

        $res = $this->curl([
            'url'  => '/api/token/',
            'type' => 'application/json',
            'data' => $updateData,
        ], 'PUT');

        $this->debug('续费响应', $res);

        if (isset($res['success']) && $res['success'] == true) {
            $this->sync();
            return ['status' => 'success', 'msg' => $res['message'] ?? '续费成功'];
        } else {
            return ['status' => 'error', 'msg' => $res['message'] ?? '续费失败'];
        }
    }

    public function packet()
    {
        $params = $this->params;
        // 购买的容量，需要映射成钱
        $capacity = $this->params["flow_packet"]["capacity"];
        $instance = $this->getInstance();
        if (!$instance) {
            $reshost = \think\Db::name("host")->field("uid")->where("id", $params["hostid"])->find();
            $description = sprintf("流量包购买成功，附加到产品失败，请手动添加临时流量。- Invoice ID:%d - Host ID:%d", $params["flow_packet"]["invoiceid"], $params["hostid"]);
            active_log_final($description, $reshost["uid"], 2, $params["hostid"]);
            return ['status' => 'error', 'msg' => '无法找到实例'];
        }

        $currentQuota = $instance['remain_quota'] ?? 0;
        $instance['remain_quota'] = intval($currentQuota) + intval($capacity);

        $res = $this->curl([
            'url'  => '/api/token/',
            'type' => 'application/json',
            'data' => $instance,
        ], 'PUT');

        if (isset($res['success']) && $res['success'] == true) {
            $this->sync();
            return ['status' => 'success', 'msg' => $res['message'] ?? '续费成功'];
        } else {
            $reshost = \think\Db::name("host")->field("uid")->where("id", $params["hostid"])->find();
            $description = sprintf("流量包购买成功，附加到产品失败，请手动添加临时流量。- Invoice ID:%d - Host ID:%d", $params["flow_packet"]["invoiceid"], $params["hostid"]);
            active_log_final($description, $reshost["uid"], 2, $params["hostid"]);
            return ['status' => 'error', 'msg' => $res['message'] ?? '续费失败'];
        }
    }
}
