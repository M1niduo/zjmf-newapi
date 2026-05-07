<?php
namespace servers\newapi\logic;

class ResponseLogic extends BaseLogic {
    public function transformAPIResponse($action, $response) {
        // 这里可以根据不同的action对响应进行不同的转换
        // 暂时直接返回原响应
        return $response;
    }
}
