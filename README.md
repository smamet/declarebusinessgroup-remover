# Wordpress mfa.declarebusinessgroup.ga removal tool

Use this tool to fix infected wordpress websites with the virus mfa.declarebusinessgroup.ga.
I developped this tool after having been infected by it. It is very basic and can be enhanced to be more user friendly. If you want to contribute, please contact me.

**This is NOT a wordpress plugin**

## Requirements
* You need to have acces to the webserver via SSH in order to be able to execute the php client.
* You need to set an up to date wordpress installation in wpmodel. You can add plugins and themes which will be added to the new install

## Usage
```
php fix_declarebusinessgroup.php [infected_folder] [destination] [url_no_protocol]
```  
**Example**
```
php fix_declarebusinessgroup.php /var/www/myinfectedsite.com/ /home/web/ www.mywebsite.com
```  
This will create a folder /home/web/mywebsite.com which will be the fixed wordpress.


## What it does

* Copy content of wpmodel/ to the destination (wpmodel should simply be an empty WP installation)
* Replaces the wp-config with correct values (If Multisite, you will need to add the ALLOW_MULTISITE parameters in wp-config manually)
* Copy plugins/, themes/ and uploads/ from infected site to cleaned site, and cleanup the folders from parasit
* Fix all MySQL malicious urls including all serialized data in wp_options
* Replace siteurl and homeurl with url given as 3rd parameter (https is hard coded, you may change it manually)

Once completed, you may use the new website. Make sure the infected one is not used anymore and there are other infected websites in the same folder.
Also update all you plugins and themes to avoid your website to be exploited again.

This tool works only for mfa.declarebusinessgroup.ga virus, not the others, but you can easily customize the tool to suit your needs.

I hope this will help!

## How to detect an infection of gutenblock-64 / stealth Virus

Looks like this ga.declarebusinessgroup.ga virus is the first step to the setup of a tool that gives control on your server to the attacker. Here is how you can see if they are using your machine for an attack. 

If you are on a linux server:

1) Check for suspicius open port
```
netstat -lntp
```  
Seeing something like this means that you where hacked:
```
tcp        0      0 0.0.0.0:4661            0.0.0.0:*               LISTEN      23752/./cron.php 
``` 

2) Identify the process by using "pwdx [pid]". The first part in 23752/./cron.php is the PID of the process.
```
pwdx 23752
``` 

3) You can then delete the folder where malicious script is, and kill the process. I suggest you change permissions on the file and folders to prevent the virus to write again during the cleanup time. This can be done by the command "chmod -R 555".
4) Inspect index.php, wp-settings.php and wp-config.php and remove all malicious includes. 
5) You will often see a 'blog/' folder with the core of the virus files in it. 
6) Also verify for any hidden 'ico'  files. (hidden files start by a dot "." on linux. Use "ls -lah" to see them)
7) Usually it creates tons on .htaccess giving access to different files clean that up.

