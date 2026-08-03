# SSO 身份认证中心

## 登录用户

```
admin / admin
```

## nginx配置示例

```
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log;
pid /run/nginx.pid;

include /usr/share/nginx/modules/*.conf;

events {
    worker_connections 1024;
}

http {
    log_format  main  '$remote_addr - $remote_user [$time_local] "$request" '
                      '$status $body_bytes_sent "$http_referer" '
                      '"$http_user_agent" "$http_x_forwarded_for"';

    access_log  /var/log/nginx/access.log  main;

    sendfile            on;
    tcp_nopush          on;
    tcp_nodelay         on;
    keepalive_timeout   65;
    types_hash_max_size 4096;

    include             /etc/nginx/mime.types;
    default_type        application/octet-stream;

    include /etc/nginx/conf.d/*.conf;

    server {
        listen       80;
        listen       [::]:80;
        server_name  _;
        root         /var/www/SsoCenter/public;

        index index.php index.html;

        include /etc/nginx/default.d/*.conf;

        location / {
            if (!-f $request_filename) {
                rewrite  ^(.*)$  /index.php?s=/$1  last;
            }
        }

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/www.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include        fastcgi_params;
        }

        error_page 404 /404.html;
        location = /404.html {
        }

        error_page 500 502 503 504 /50x.html;
        location = /50x.html {
        }
    }
}

```

## php-fpm配置示例

### /usr/lib/systemd/system/php-fpm.service

```
[Unit]
Description=The PHP FastCGI Process Manager
After=network.target

[Service]
Type=simple
PIDFile=/var/run/php-fpm.pid
ExecStart=/usr/local/php83/sbin/php-fpm --nodaemonize --fpm-config /usr/local/php83/etc/php-fpm.conf
ExecReload=/bin/kill -USR2 $MAINPID
PrivateTmp=true
ProtectSystem=full
PrivateDevices=true
ProtectKernelModules=true
ProtectKernelTunables=true
ProtectControlGroups=true
RestrictRealtime=true
RestrictAddressFamilies=AF_INET AF_INET6 AF_NETLINK AF_UNIX
RestrictNamespaces=true

[Install]
WantedBy=multi-user.target
```

### /usr/local/php83/etc/php-fpm.conf

```
;;;;;;;;;;;;;;;;;;;;;
; FPM Configuration ;
;;;;;;;;;;;;;;;;;;;;;

;;;;;;;;;;;;;;;;;;
; Global Options ;
;;;;;;;;;;;;;;;;;;

[global]
pid = /var/run/php-fpm.pid
error_log = /var/log/php-fpm/error.log
daemonize = yes

;;;;;;;;;;;;;;;;;;;;
; Pool Definitions ;
;;;;;;;;;;;;;;;;;;;;

include=/usr/local/php83/etc/php-fpm.d/*.conf
```

### /usr/local/php83/etc/php-fpm.d/www.conf

```
[www]
user = nginx
group = nginx

listen = /var/run/www.sock
listen.owner = nginx
listen.group = nginx
listen.allowed_clients = 127.0.0.1
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 5000

php_admin_value[error_log] = /var/log/nginx/default-error_log
php_admin_flag[log_errors] = on
php_admin_value[error_reporting] = E_ALL & ~E_NOTICE & ~E_WARNING

php_value[session.save_handler] = files
php_value[session.save_path]    = /var/lib/php/session
php_value[soap.wsdl_cache_dir]  = /var/lib/php/wsdlcache

```

## php扩展列表

```
[PHP Modules]
bcmath
Core
ctype
curl
date
dom
fileinfo
filter
ftp
gd
hash
iconv
intl
json
libxml
mbstring
mysqli
mysqlnd
openssl
pcntl
pcre
PDO
pdo_mysql
pdo_sqlite
Phar
posix
random
Reflection
session
SimpleXML
soap
sockets
SPL
sqlite3
standard
tokenizer
xml
xmlreader
xmlwriter
zip
zlib

[Zend Modules]


```
