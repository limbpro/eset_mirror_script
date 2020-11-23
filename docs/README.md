# Documentation
- Usage
- Configuration file nod32ms.conf
- Apache VirtualHost Configuration
- NGinx VirtualHost Configuration
- System Configuration

## Usage
You need dedicated server (home, network provider or hosting)

User with sudo right and allow to run apache2/nginx daemon, same as www-data on Debian/Ubuntu Systems

Go to folder /var/www/ or other public html root folder

Clone repo by command: `git clone https://github.com/Kingston-kms/eset_mirror_script.git`

Move to folder: `cd eset_mirror_script && cp nod32ms.conf.eng nod32ms.conf`

Edit nod32ms.conf.

## Configuration File nod32ms.conf

File nod32ms.conf is copy of file nod32ms.conf.eng or nod32ms.conf.rus

File structure - INI, sections and parameters contains comments. 

## System configuration

Cron setup, update every 4 hours

`0 */4 * * * cd /var/www/eset_mirror_script/ && /usr/bin/php /var/www/eset_mirror_script/update.php`

## Apache VirtualHost Configuration
Example configuration file:
```
<VirtualHost *:80>
         ServerName nod32.domain.ru
         ServerAlias nod.domain.ru, eset.domain.ru, update.domain.ru
 
         ServerAdmin webmaster@domain.ru
         DocumentRoot /var/www/eset_mirror_script/www
         <Directory "/var/www/eset_mirror_script/www">
 
                 Options FollowSymLinks
                 AllowOverride All
                 Require all granted
 
                 RewriteEngine on
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ 3
                 RewriteRule ^update.ver$ /eset_upd/update.ver [L]
 
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ ([4-9])
                 RewriteRule ^eset_upd/update.ver$ /eset_upd/v%1/update.ver [L]
 
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ ([4-9])
                 RewriteRule ^update.ver$ /eset_upd/v%1/update.ver [L]
 
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ 10 [OR]
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ 11
                 RewriteRule ^eset_upd/update.ver$ /eset_upd/v10/update.ver [L]
 
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ 10 [OR]
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ 11
                 RewriteRule ^update.ver$ /eset_upd/v10/update.ver [L]
 
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ 12
                 RewriteRule ^eset_upd/update.ver$ /eset_upd/v12/update.ver [L]
 
                 RewriteCond %{HTTP_USER_AGENT} ^.*Update.*BPC\ 12
                 RewriteRule ^update.ver$ /eset_upd/v12/update.ver [L]
 
         </Directory>
 
         ErrorLog /var/www/eset_mirror_script/log/apache-error.log
         CustomLog /var/www/eset_mirror_script/log/apache-access.log combined
 
 </VirtualHost>
```
This file need to place in folder `/etc/apache2/sites-available/` and name of file nod32ms-site.conf

Edit file for you domain, folder and etc.

Before start Apache2 Server you need to create log and www folders.
Then in console run command (need sudo or root access): `a2ensite nod32ms-site.conf` and restart Apache2 Service

## Nginx Configuration File
Example configuration file:
```
server {

        listen 80;
        listen [::]:80;

        root /var/www/eset_mirror_script/www;

        # Add index.php to the list if you are using PHP
        index index.html index.htm;

        server_name nod32.domain.ru update.domain.ru;

        location / {

          if ($http_user_agent ~ "^.*Update.*BPC\s+3\..*"){
            rewrite ^/update.ver$ /eset_upd/update.ver break;
            rewrite ^/eset_upd/update.ver$ /eset_upd/update.ver break;
          }

          if ($http_user_agent ~* "^.*Update.*BPC\s+(\d?)\..*$"){
            set $ver $1;
          }

          if ($http_user_agent ~* "^.*Update.*BPC\s+(\d+)\..*$"){
             set $ver $1;
          }

          if ($ver = 11) {
              set $ver 10;
          }

          rewrite ^/update.ver$ /eset_upd/v$ver/update.ver break;
          rewrite ^/eset_upd/update.ver$ /eset_upd/v$ver/update.ver break;

        }

        access_log /var/www/eset_mirror_script/log/nginx-access.log;
        error_log /var/www/eset_mirror_script/log/nginx-error.log;

}
```
Place this file in `/etc/nginx/sites-available/` and name nod32ms-site.conf

Create symlink to this file `ln -s /etc/nginx/sites-available/nod32ms-site.conf /etc/nginx/sites-enabled/nod32ms-site.conf`

Before start NGinx Server you need to create log and www folders.
Restart nginx service
