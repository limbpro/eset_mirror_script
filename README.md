# eset_mirror_script
Script to run own eset mirror

# Requirements
- PHP 5.6+ or 7+
- unrar
- nginx(sample configurations in this file below) or other web-server

# Installations
- copy nod32ms.conf.%lang% -> nod32ms.conf
- edit lines in nod32ms.conf

# If you have valid login:password
- set them into log/nod_keys.valid in format login:password:version

# Run
- run php update.php

# Debuging
- set in nod32ms.conf log_level = 5
- run php debuging_run.php to see all messages at console

# PHP modules
- curl
- fileinfo
- iconv
- mbstring
- openssl
- pcre
- SimpleXML
- sockets
- zlib

# Cron simple job
@hourly **[path to php]** **[path to update.php]**

# Nginx simple configuration
map $http_user_agent $dir {

 default                        /index.html;

 ~^(ESS.*BPC.3)                 /eset_upd/update.ver;

 ~^(.*Update.*BPC\ (?<ver>\d+))	/eset_upd/v$ver/update.ver;

}

server {

 listen 2221;
 
 server_name **[host]**;
 

 access_log /var/log/nginx/**[host]**-access.log;
 
 error_log /var/log/nginx/**[host]**-error.log;
 
 index index.php index.html index.htm;
 
 root **[web_dir from nod32ms.conf]**;
 
 
 location / {
 
  root **[web_dir from nod32ms.conf]**;
  
 }

 location /update.ver {
 
  rewrite ^/update.ver$ $dir redirect;
  
 }

 location ~ /\.ht {
 
  deny  all;
  
 }
 
}
