<?php

namespace servers\newapi\logic;

class BaseLogic {
    protected $params;

    public function __construct($params) {
        $this->params = $params;
    }

    protected function debug($message, $data = null) {
        if (!defined('NEWAPI_DEBUG') || !NEWAPI_DEBUG) {
            return;
        }

        $log = '[NEWAPI-DEBUG] ' . $message;
        if ($data !== null) {
            $log .= ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        error_log($log);
    }

    public function getInstance($nocache = false) {
        $hostname = $this->params['domain'] ?? '';
        if ($hostname === '') {
            $this->debug('缺少domain参数，无法获取实例');
            return null;
        }
      
        $data = [
            'url'  => '/api/token/search',
            'type' => 'application/json',
            'data' => [
                'keyword' => $hostname,
            ],
        ];

        $token = cache($hostname);
        
        if ($token != false && !$nocache) {
            return $token;
        }
     
        $res = $this->curl($data, 'GET');

        if (!isset($res['success']) || $res['success'] != true || !isset($res['data']) || !is_array($res['data'])) {
            return null;
        }
        foreach ($res['data']['items'] as $instance) {
            if (isset($instance['name']) && $instance['name'] == $hostname) {
                $token = $instance;
                break;
            }
        }
        if (!isset($token)) {
            $this->debug('无法找到实例ID', ['hostname' => $hostname, 'response' => $res]);
            return null;
        }

        $data = [
            'url'  => '/api/token/'.$token['id'].'/key',
            'type' => 'application/json',
            'data' => [],
        ];
        $key = $this->curl($data, 'POST');
        
        if (!isset($key['success']) || $key['success'] != true) {
            $this->debug('找到实例ID但找key失败', ['hostname' => $hostname, 'response' => $key]);
            return null;
        }
        $token['key'] = $key['data']['key'];
        cache($hostname, $token, 300);
        return $token;
    }

    protected function curl($data = [], $request = 'POST', $repeat = false) {
        $params = $this->params;
        $curl = curl_init();

        $protocol = !empty($params['secure']) ? 'https' : 'http';
        $port = $protocol == 'https' ? 443 : 80;
        $host = $params['server_ip'] ?? ($params['dedicatedip'] ?? '');
        $url = $protocol . '://' . $host . ':' . $port . $data['url'];

        $postFields = null;
        if ($request === 'POST' || $request === 'PUT') {
            $postFields = $data['data'] ?? null;
            if ($postFields !== null && ($data['type'] ?? '') === 'application/json' && is_array($postFields)) {
                $postFields = json_encode($postFields);
            }
        } elseif ($request === 'GET' && !empty($data['data'])) {
            if (is_array($data['data'])) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data['data']);
            } elseif (is_string($data['data'])) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $data['data'];
            }
        }

        $curlOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $request,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: ' . ($data['type'] ?? 'application/json'),
            ],
        ];

        $token = $params['accesshash'] ?? ($params['server_password'] ?? null);
        if (!empty($token)) {
            $curlOptions[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . $token;
        }

        if (!empty($params['server_username'])) {
            $curlOptions[CURLOPT_HTTPHEADER][] = 'New-Api-User: ' . $params['server_username'];
        }

        $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
        $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
        $curlOptions[CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2;

        curl_setopt_array($curl, $curlOptions);

        if ($postFields !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        }

        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);

        curl_close($curl);

        if ($errno) {
            $this->debug('CURL错误', [
                'url' =>  $url,
                'code' => $errno,
                'message' => $curlError,
                'success' => false,
            ]);
            return [
                'code' => $errno,
                'message' => $curlError,
                'success' => false,
            ];
        }
    
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            $decoded = [
                'url' =>  $url,
                'code' => $httpCode,
                'message' => '响应解析失败:'.$response,
                'success' => false,
            ];
        }
        return $decoded;
    }

    public function testLink() {
        $params = $this->params;
        $this->debug('开始测试API连接', $params);

        $data = [
            'url'  => '/api/status',
            'type' => 'application/json',
            'data' => [],
        ];

        $res = $this->curl($data, 'GET');

        if ($res === null) {
            return [
                'status' => 200,
                'data'   => [
                    'server_status' => 0,
                    'msg'           => '连接失败: 无法连接到服务器',
                ],
            ];
        } elseif (isset($res['success']) && $res['success'] == true) {
            return [
                'status' => 200,
                'data'   => [
                    'server_status' => 1,
                    'msg'           => '连接成功',
                ],
            ];
        } else {
            return [
                'status' => 200,
                'data'   => [
                    'server_status' => 0,
                    'msg'           => '连接失败: 响应格式异常',
                ],
            ];
        }
    }
}

