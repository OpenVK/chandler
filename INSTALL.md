You will need the following software for the installation:
* Apache Web Server (2.4 or later) or NGINX
* Composer (for setup)
* `ln` program (for setup, comes with GNU Coreutils)
* PHP (7.3 or later)
* YAML extension
* Percona Server or MySQL 8+ (with legacy auth mechanism)

Please note that libchandler uses Sodium PHP extension.
This extension is included in default php7.3+ setup, but some hosting providers disable it.
Please, contact your hosting provider and ask them whether sodium is available.
Also, some plugins may require some additional dependencies from Packagist/NPM, so, you
may need to have Yarn installed to correctly setup dependencies.

Installation steps:
* Clone this repo or just download this repo as archive and extract it
* Run `composer install`
* Download plugin, that provides Web App and extract it to `extensions/available`
* Symlink plugin folder from `extensions/available` to `extensions/enabled`
* Edit example config and remove `-example` from its name
* * Set root app to your Web App plugin
* * Generate your secret key (Random string, which length is exactly 128 characters)
* Create new VHost and point it's documentroot to `htdocs` folder
* * When using NGINX - use [this configuration file](https://github.com/openvk/chandler/blob/master/install/nginx.conf) as an example.
