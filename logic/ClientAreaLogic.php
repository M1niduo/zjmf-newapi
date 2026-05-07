<?php
namespace servers\newapi\logic;

class ClientAreaLogic extends BaseLogic {
    public function getClientArea() {
        return [
            'info'    => ['name' => 'Token 信息'],
            'setting' => ['name' => 'Token 配置'],
            'logs'    => ['name' => 'Token 日志'],
        ];
    }

    public function getClientAreaOutput($key) {
        $params = $this->params;
        $action = $_GET['action'] ?? null;

        $this->debug('ClientAreaOutput调用', ['key' => $key, 'action' => $action]);

        if ($action) {
            $this->handleAction($action);
        }

        $protocol = ($params['secure'] ?? 1) ? 'https' : 'http';
        $apiHost = $params['dedicatedip'] ?? ($params['server_ip'] ?? '');
        $apiBaseUrl = $protocol . '://' . $apiHost . '/v1/';

        $instance = $this->getInstance();

        $groupsData = $this->curl([
            'url'  => '/api/user/self/groups',
            'type' => 'application/json',
            'data' => [],
        ], 'GET');

        $modelsData = $this->curl([
            'url'  => '/api/user/models',
            'type' => 'application/json',
            'data' => [],
        ], 'GET');

        $vars = [
            'Token'      => $instance,
            'Detail'     => ['host_data' => $params],
            'ApiBaseUrl' => $apiBaseUrl,
            'Groups'     => $groupsData['data'] ?? [],
            'Models'     => $modelsData['data'] ?? [],
        ];

        switch ($key) {
            case 'info':
                return [
                    'template' => 'templates/info.tpl',
                    'vars'     => $vars,
                ];
            case 'setting':
                return [
                    'template' => 'templates/settings.tpl',
                    'vars'     => $vars,
                ];
            case 'logs':
                return [
                    'template' => 'templates/logs.tpl',
                    'vars'     => $vars,
                ];
        }
    }

    protected function handleAction($action) {
        $this->debug('处理API请求', ['action' => $action, 'domain' => $this->params['domain'] ?? null]);

        switch ($action) {
            case 'save_token':
                $this->handleSaveTokenAction();
                break;
            case 'groups':
                $this->handleGroupsAction();
                break;
            case 'models':
                $this->handleModelsAction();
                break;
            case 'logs':
                $this->handleLogsAction();
                break;
            default:
                $this->outputJsonResponse(['success' => false, 'message' => '不支持的操作类型']);
        }
    }

    protected function handleSaveTokenAction() {
        $input = array_merge($_GET, $_POST);
        $this->debug('保存请求', $input);

        $currentToken = $this->getInstance();
        if (!$currentToken || !is_array($currentToken)) {
            $this->outputJsonResponse(['success' => false, 'message' => '获取令牌信息失败']);
        }

        $updateData = [
            'id' => $currentToken['id'],
            'key' => $currentToken['key'] ?? '',
            'status' => $currentToken['status'] ?? 1,
            'name' => $currentToken['name'] ?? '',
            'model_limits_enabled' => $currentToken['model_limits_enabled'] ?? false,
            'model_limits' => $currentToken['model_limits'] ?? '',
            'group' => $input['group'] ?? ($currentToken['group'] ?? 'default'),
            'cross_group_retry' => isset($input['cross_group_retry']) && $input['cross_group_retry'] == '1',
            'allow_ips' => $input['allow_ips'] ?? ($currentToken['allow_ips'] ?? ''),
        ];

        // 保护关键字段：仅使用当前Token已有值，防止误写为0或默认值
        if (array_key_exists('unlimited_quota', $currentToken)) {
            $updateData['unlimited_quota'] = (bool)$currentToken['unlimited_quota'];
        }
        if (array_key_exists('remain_quota', $currentToken)) {
            $updateData['remain_quota'] = $currentToken['remain_quota'];
        }
        if (array_key_exists('expired_time', $currentToken)) {
            $updateData['expired_time'] = $currentToken['expired_time'];
        }

        $res = $this->curl([
            'url'  => '/api/token/',
            'type' => 'application/json',
            'data' => $updateData,
        ], 'PUT');

        $this->outputJsonResponse($this->normalizeApiResponse($res));
    }

    protected function handleGroupsAction() {
        $res = $this->curl([
            'url'  => '/api/user/self/groups',
            'type' => 'application/json',
            'data' => [],
        ], 'GET');

        $this->outputJsonResponse($this->normalizeApiResponse($res));
    }

    protected function handleModelsAction() {
        $res = $this->curl([
            'url'  => '/api/user/models',
            'type' => 'application/json',
            'data' => [],
        ], 'GET');

        $this->outputJsonResponse($this->normalizeApiResponse($res));
    }

    protected function handleLogsAction() {
        $instance = $this->getInstance();
        $tokenName = $instance['name'];
   
        if(!isset($tokenName)) {
            $this->outputJsonResponse(['success' => false, 'message' => '未获取到实例']);
        }

        $startTimestamp = isset($_GET['start_timestamp']) ? intval($_GET['start_timestamp']) : strtotime(date('Y-m-d 00:00:00'));
        $endTimestamp = isset($_GET['end_timestamp']) ? intval($_GET['end_timestamp']) : time();
        $query = [
            'p' => isset($_GET['p']) ? intval($_GET['p']) : 1,
            'page_size' => isset($_GET['page_size']) ? intval($_GET['page_size']) : 5,
            'type' => 2,
            // 'group' => $_GET['group'] ?? '',
            // 'model_name' => $_GET['model_name'] ?? '',
            'token_name' => $tokenName,
            'start_timestamp' => $startTimestamp,
            'end_timestamp' => $endTimestamp,
        ];

        if ($query['p'] <= 0) {
            $query['p'] = 1;
        }
        if ($query['page_size'] <= 0) {
            $query['page_size'] = 5;
        }

        $res = $this->curl([
            'url'  => '/api/log/self',
            'type' => 'application/json',
            'data' => $query,
        ], 'GET');

        $this->outputJsonResponse($this->normalizeApiResponse($res));
    }

    protected function normalizeApiResponse($res) {
        if ($res === null) {
            return ['success' => false, 'message' => '连接服务器失败'];
        }

        if (!is_array($res)) {
            return ['success' => false, 'message' => '服务器返回了无效的响应格式'];
        }

        return $res;
    }

    protected function outputJsonResponse($data) {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo json_encode($data);
        exit;
    }
}
