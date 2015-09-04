# mysqldiff
A simple tool that helps to compare 2 databases using Doctrine DBAL.

# Installation
Just clone it and do a : 
```bash
composer update
```

Create a Vhost that'll have www/ as a DocumentRoot and open your browser. Set the information

Disabled options are because Doctrine doesn't compare Table's properties such as Engine, Charset or Comment. 

# Todo
A lot, including:
* Improve the GUI
* Unit Tests
* Do what Doctrine doesn't know how to do (MEDIUMINT != INT for example).

