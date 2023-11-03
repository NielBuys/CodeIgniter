###################
Fork of CodeIgniter
###################

CodeIgniter is an Application Development Framework - a toolkit - for people
who build web sites using PHP. Its goal is to enable you to develop projects
much faster than you could if you were writing code from scratch, by providing
a rich set of libraries for commonly needed tasks, as well as a simple
interface and logical structure to access these libraries. CodeIgniter lets
you creatively focus on your project by minimizing the amount of code needed
for a given task.

*******************
Release Information
*******************

https://github.com/NielBuys/CodeIgniter/releases

*******************
Server Requirements
*******************

PHP version 7.2 or newer (PHP 8.2 ready) is recommended.

************
Installation
************

CodeIgniter is installed in four steps:

1. npm install nielbuys/framework
2. Change "codeigniter/framework" to "nielbuys/framework" in the "composer.json" file.
3. Change in main "index.php" the following line from "$system_path = 'vendor/codeigniter/framework/system';" to "$system_path = 'vendor/nielbuys/framework/system';"
4. Then run composer update.

or

1. Unzip the package.
2. Upload the CodeIgniter folders and files to your server. Normally the index.php file will be at your root.
3. Open the application/config/config.php file with a text editor and set your base URL. If you intend to use encryption or sessions, set your encryption key.
4. If you intend to use a database, open the application/config/database.php file with a text editor and set your database settings.

If you wish to increase security by hiding the location of your CodeIgniter files you can rename the system and application folders to something more private. If you do rename them, you must open your main index.php file and set the $system_path and $application_folder variables at the top of the file with the new name you’ve chosen.

For the best security, both the system and any application folders should be placed above web root so that they are not directly accessible via a browser. By default, .htaccess files are included in each folder to help prevent direct access, but it is best to remove them from public access entirely in case the web server configuration changes or doesn’t abide by the .htaccess.

If you would like to keep your views public it is also possible to move the views folder out of your application folder.

After moving them, open your main index.php file and set the $system_path, $application_folder and $view_folder variables, preferably with a full path, e.g. ‘/www/MyUser/system’.

One additional measure to take in production environments is to disable PHP error reporting and any other development-only functionality. In CodeIgniter, this can be done by setting the ENVIRONMENT constant, which is more fully described on the security page.

*******
License
*******

Please see the `license
agreement <https://github.com/NielBuys/CodeIgniter/blob/3.1-stable/license.txt>`_.

*********
Resources
*********

-  `User Guide <https://codeigniter.com/userguide3/>`_
-  `Contributing Guide <https://github.com/bcit-ci/CodeIgniter/blob/develop/contributing.md>`_
-  `Language File Translations <https://github.com/bcit-ci/codeigniter3-translations>`_
-  `Community Forums <http://forum.codeigniter.com/>`_
-  `Community Wiki <https://github.com/bcit-ci/CodeIgniter/wiki>`_
-  `Community Slack Channel <https://codeigniterchat.slack.com>`_

Report security issues to our `Security Panel <mailto:security@codeigniter.com>`_
or via our `page on HackerOne <https://hackerone.com/codeigniter>`_, thank you.

***************
Acknowledgement
***************

The CodeIgniter team would like to thank EllisLab, all the
contributors to the CodeIgniter project and you, the CodeIgniter user.
