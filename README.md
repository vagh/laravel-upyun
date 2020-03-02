## 安装
```php
$ composer install vagh/laravel-upyun
```

## 配置
在项目中 `config/filesystems.php` 中 `disks` 加入新的驱动
```php
'upyun' => [
    'driver' => 'upyun',
    // bucket 名称
    'bucket' => '',
    // 操作员用户名
    'operator_name' => '',
    // 操作员密码
    'operator_password' => '',
    // 资源域名
    'domain' => '',
    // 是否使用 https 协议 默认否
    'use_ssl' => true
],
```
