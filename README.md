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

