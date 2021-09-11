# FF - Forms PHP Framework #

***WARNING: The repository is not fully loaded with the code, it will be done gradually. As today, just the code needed for the WebSocket server (one of the framework's libraries) is available.***

### What is this repository for? ###

FF is a PHP Framework developed in many years of work in the field.  Its aim is to speed up the process of creating web applications, reducing the coding to what is strictly needed. Between style and practicality, it always gives priority to the latter.

Its nature is heavily modular, so entire functionalities can be added just including a directory in the project. The same goes for the visualization. Just downloading a theme not only the look but also the functionality and the behaviour of the web app could change with no need to do a single change to the code. 

### How do I get the code? ###

There are two different options

#### Single modules with composer (PREFERRED)

If you don't have setup your composer.json yet, do a <code>composer init</code> and follow the instructions

Install the custom repository in your composer.json with the command:

<code>
composer config repositories.ff composer https://www.ffphp.com/composer/splitpackages.json
</code>

Then require the individual modules you want. For instance:

<code>
composer require ff/libs_pwa_websocket_server:1.*
</code>


and that's all. Don't forget to include the generated file <code>vendor/autoload.php</code>

#### Everything (NOT SUGGESTED)

You can clone the whole repository with git

<code>
git clone https://github.com/SamueleD78/ff.git
</code>

or Just install it through packagist

<code>
composer require samueled78/ff
</code>


but that's really a lot, and in the end you will find yourself deleting a lot of dirs, so.. go with the preferred.

### Which sub-packages are available as today? ###

Here is an updated list. All the packages marked with an (*) are support packages, usually included by other main packages (so it's unlikely they will be required directly).

Namespace | Package Name | Porpouse
-----|--------------|---------
FF\Core\Common | core_common (*) | common files
FF\Core\Sapi | core_sapi | Server Application Programming Interface. All the files needed to route and handle various kind of requests
FF\Libs\PWA\WebSocket\Common | libs_pwa_websocket_common (*) | WebSocket Server - common files
FF\Libs\PWA\WebSocket\Server | libs_pwa_websocket_server | WebSocket Server
FF\Libs\PWA\WebSocket\ControlClient | libs_pwa_websocket_controlclient | WebSocket Server - Control Client API for controlling the server from an app

### Who do I talk to? ###

* Samuele Diella <samuele.diella@gmail.com>