<?php

// 跨域资源共享(CORS)配置文件
// 控制哪些外部域名/源可以访问应用程序API
return [
    // 允许跨域请求的路径
    'paths' => ['api/*', '/sanctum/csrf-cookie'],

    // 匹配上述路径时允许的请求来源
    'allowed_origins' => ['http://localhost:3000'],

    // 匹配上述路径时允许的请求方法
    'allowed_methods' => ['*'],

    // 匹配上述路径时允许的请求头
    'allowed_headers' => ['*'],

    // 在响应中可以公开的头信息
    'exposed_headers' => [],

    // 允许的最大预检请求缓存时间（秒）
    'max_age' => 0,

    // 指定是否允许发送凭据（cookies、HTTP认证和客户端SSL证书）
    // 注意：当设为true时，allowed_origins不能设为*
    'supports_credentials' => true,
]; 