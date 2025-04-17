Nicochan
========================================================

There is a handful of themes I didn't tested bc we don't use them.
This includes: categories, index, js_frameset, recent, basic, frameset

## Info regardings existing installations
You'll need to run the script `tools/update_embed.php` and `tools/update_password` to update current embeds and hashing passwords.

Requirements
------------
1.	PHP >= 8.0 (PHP 8.1+ is in beta, but working so far)
2.	MySQL/MariaDB
3.	[mbstring](https://www.php.net/manual/en/mbstring.installation.php)
4.	[PHP GD](https://www.php.net/manual/en/intro.image.php)
5.	[PHP PDO](https://www.php.net/manual/en/intro.pdo.php)
6.	[PHP BC Math](https://www.php.net/manual/en/book.bc.php)
7.	[PHP Intl](https://www.php.net/manual/en/book.intl.php)
8. 	[PHP curl](https://www.php.net/manual/en/book.curl.php)
9. 	[Composer](https://getcomposer.org/download/)
10. [Blockhash](https://github.com/commonsmachinery/blockhash) - for hashing images
11.	A Unix-like OS, preferrably FreeBSD or Linux

We try to make sure vichan is compatible with all major web servers. vichan does not include an Apache ```.htaccess``` file nor does it need one.

### Recommended
1.	MariaDB server >= 10.3.22
2.	ImageMagick (command-line ImageMagick or GraphicsMagick preferred).
3.	[APCU (Alternative PHP Cache)](https://php.net/manual/en/book.apcu.php),
	[XCache](https://xcache.lighttpd.net/),
	[Memcached](https://www.php.net/manual/en/intro.memcached.php) or
	[Redis](https://redis.io/)

Installation
-------------
1.	Install the follow dependencies:
	```
	apt-get install ffmpeg graphicsmagick gifsicle php8.0-fpm php8.0-cli php8.0-mysql php8.0-gd php8.0-intl php8.0-mbstring php8.0-bcmath php8.0-curl
	```
2.	Download and extract Tinyboard to your web directory or get the latest
	development version with:

        git clone git://github.com/perdedora/nicochan.git

3.	run ```composer install``` inside the directory
4.	Navigate to ```install.php``` in your web browser and follow the
	prompts.
5.	Nicochan should now be installed. Log in to ```mod.php``` with the
	default username and password combination: **admin / password**.

Please remember to **change** the account password.

See also: [Configuration Basics](https://github.com/fallenPineapple/NPFchan/wiki/config).

Upgrade
-------
To upgrade from any version of Tinyboard or vichan or NFPchan or Nicochan:

Either run ```git pull``` to update your files if you use git, or replace all
your files in place (don't remove boards etc.) and then run ```install.php```.

IF YOU'RE UPGRADING FROM ANOTHER VICHAN/NPFCHAN INSTANCE, YOU HAVE TO RUN THE UPDATE SCRIPTS IN THE FOLDER "tools" AND INSTALL intl extension for php.

To migrate from a Kusaba X board, use http://github.com/vichan-devel/Tinyboard-Migration

CLI tools
-----------------
There are a few command line interface tools, based on Tinyboard-Tools. These need
to be launched from a Unix shell account (SSH, or something). They are located in a ```tools/```
directory.

You actually don't need these tools for your imageboard functioning, they are aimed
at the power users. You won't be able to run these from shared hosting accounts
(i.e. all free web servers).

WebM support
------------
Read `inc/lib/webm/README.md` for information about enabling webm.

License
--------
See [LICENSE.md](http://github.com/perdedora/nicochan/blob/master/LICENSE.md).
