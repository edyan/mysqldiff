# mysqldiff
A simple tool that helps to compare 2 databases using Doctrine DBAL.

# Installation
* Just clone it and do a :
```bash
composer install --no-dev
```
* Create a Vhost that'll have www/ as a DocumentRoot and open your browser. Set the information
* Disabled options are because Doctrine doesn't compare Table's properties such as Engine, Charset or Comment.
* Open http://vhost.name/ on your browser. To avoid having "index.php" for each URL, copy .htaccess-dist to .htaccess in www/

# Todo
A lot, including:
* Improve the GUI
* Do what Doctrine doesn't know how to do (MEDIUMINT != INT for example).

