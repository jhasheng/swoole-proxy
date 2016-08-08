# swoole-proxy
基于 Swoole 的 HTTP(S) 代理，支持 SOCKS5


# 使用方法
```bash
git clone https://github.com/jhasheng/swoole-proxy
cd swoole-proxy
composer dump-autoload

php example/ss.php 启动socks server
php example/proxy.php 启动代理服务器
```

HTTP 代理，可独立使用，支持HTTP/HTTPS，可使用二级代理(SOCKS5)

[ ] HTTP 反向代理
[ ] HTTPS 反向代理
[ ] 二级代理(SOCKS5，TCP 版，无身份验证版)
[x] MITM 中间人攻击，对 HTTPS 数据进行解密，对原理理解的不是很透彻，求高手指点
[x] 控制台日志输出优化


```php
$proxy = new \SS\SwooleProxy();
// 二级代理
$proxy->setSocksServer('0.0.0.0:10005');
$proxy->listen(10004);
```


SOCKS 代理，可独立使用，TCP代理，介于应用层与传输层之间，不关心应用层使用的何种协议，理论上应用层协议都支持

[ ] SOCKS5 实现，未严格按 `rfc1928` 文档编写，后期完善
[x] UDP 支持，不打算支持
[x] 身份验证，后期加入
[x] SOCKS4
[x] SOCKS4a
[x] 数据传输加密

```php
$ss = new \SS\SwooleServer();
$ss->start();
```