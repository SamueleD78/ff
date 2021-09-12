# FF - Forms PHP Framework #

## WebSocket Server ##

This is the FF implementation of a multi-purpose WebSocket server.  

Aside from the basic Websocket protocol, that is to be considered and "underlying" protocol 
used just for the connection management and the data transmission, 
this implementation doesn't provide any kind of protocol implementation.
So it doesn't manage message types aside from the ones embedded in the websocket protocol,
it doesn't manage user authentication or authorization to services (it doesn't manage users at all),
and so on.  
For this reason, it's a perfect basis for developing various websocket implementation,
like chat servers, notification servers, etc.  

Considering that the servers are intended to run as standalone processes, so not through a normal request
to a http server like apache, a Control Client API is provided, ready to be embedded in any kind of web applications
(even not based on FF). It's an object-oriented API able to connect and communicate with
the server processes for listing the connected clients, sending messages and so on, all within a
normal PHP app.  

### Basic concepts ###

When in the code and in the documentation we are talking about a **Server**, we are referring to a 
"Websocket Server" made with this implementation. 
A server is basically a standalone process made with a php script running from the CLI (or started as a service)
that open a network port and listen for incoming connections (usually browsers).  

We could of course start various servers listening on different ports at the same time,
but that's not necessary, because following the Websocket protocol RFC guidelines 
[#6455](https://datatracker.ietf.org/doc/html/rfc6455)
this implementation is able to manage different "**Services**" on the same process with the same network port.  
This is really helpful, because in a production environment it couldn't be so easy to have
as many ports as we want, and even more important having many services served on the same process
enable to establish a communication between services.  

One example could be the one of a web application that offers a chat functionality for the users.  
The chat itself is a service, with his own specific protocol.  
Now just imagine we want real-time notifications of what is happening in the chat
inside the site admin area, for the connected admins, but without having the admins
taking actually part in the chat itself.  
We could create on the same server a different service just for the admins, with a
much simpler protocol and a totally different system of authentication.  
Being both the services in the same server instance, the inter-operation between
the two services is far easier.  

According to the RFC, the services must be identified by the path used to connect to the server.  
In order to connect to a server an url needs to be provided. For instance to connect
to a Webserver running on the server www.php.com on the port 9100 over SSL, the url will be
<code> wss://www.php.com:9100 </code> (without SSL it would have been ws://).  
Following our example, in order to differentiate the two services, we could use two different urls, like:
* <code> wss://www.php.com:9100/chat </code> for the chat
* <code> wss://www.php.com:9100/admin </code> for the notifications

Internally, the server will use a routing mechanism to recognize the two different paths and
to attach the connection to the proper service, using the standard FF router object.  

When a client (like the javascript from a browser) connect to the server, the connection passes through two stages.  

1) *The handshake*. In this phase an "agnostic" **Websocket** object is created to manage the single connection.  
Things like the protocol version are checked, and all the data needed to understand the service are 
parsed.
2) *The service routing*. After the proper service is detected, a **Client** object specific to that service is created.
The Websocket object is linked with it and the final object is attached to the service.
Since that moment, the Client will be effectively watched for data transfers.

So far, we have described *four* of the main objects used in this implementation:
* the **Server** object, manager of everything related to the connections. It opens a port and listen for the 
clients to connect.
* the **Service** objects, container of the Clients using a specific functionality
* the **Websocket** objects, low-level managers of the single connection to a client
* the **Client** objects, specific to the service used by the single connection, each one of em linked  
with a Websocket object and actual placeholders of the code related to specific functionalities

The last players in this scenario are the *Control objects*. As stated before, this is something that is not 
described by the RFC, but provided in order to make the software manageable.  

Being the Server a standalone running process, it's not directly connected to any web application,
that usually are provided under a web server like Apache. But it could be useful to manage the server from 
a normal application.  
Following our chat example, we could want to disconnect instantly any connected user when his account is deleted.
Or we could want to broadcast a message to all the clients when an action happens in the data entry, like a new post
is made. Actually this could be used to make the clients connected to a data-entry app instantly notified of what
is happening. Just imagine: two users are connected to the same data-entry page. One of them do a change to the
data and the other one is instantly notified that the data are changed, and if it's possible the data 
could be refreshed instantly without the need of any user interaction or polling mechanism. And so on.

In order to do such kind of things, the application need an API to communicate to the server, 
and the server need an interface for the API that is separate from the normal connected clients.  

Those two concepts are managed by the **ControlIF** and **ControlClient** objects.  

* ControlIF (Control Interface) is the object listening on the Websocket Server for the API connections.
* ControlClient is the API object used by the application to connect to the Websocket Server.

One server could use many ControlIF. Maybe we want to have different ways to connect to the same
Server, for instance through a TCP port and through a UNIX sock.

Each ControlIF needs to use an **Authenticator** in order to allow or deny the API connections.
There is a simple authenticator provided with the framework,
based on classic id/secret pairs (username/password). But any kind of authenticator could be made: based on OAuth,
on the system users, on a DB table and so on. Different ControlIF can share the same Authenticator.

The Authenticator will be responsible not only for the authentication process, but also for the authorization on the
services. That is useful if we want to give permission to some apps to access only one service instead of every service
present in the server, just like it happens with the users and the databases in a software like MySql.

Each time a connection between an application and the ControlIF is made, a **ControlClient** object is created into the
Server. This object will hold everything is needed to manage the connection with the application, and it must not be
confused for the ControlClient API object, that lies in a totally different package, and it's solely used by 
the applications to connect *to* the server. Different ControlIF can customize the type of ControlClient they want,
in order to implement custom commands aside from the ones provided.

So, we have described the last *three* server objects:
* The *ControlIF* object, allow and manage external applications to connect to the Server. One Server could
have multiple ControlIF running
* The *Authenticator* object, it's used by the ControlIF to authenticate and authorize the applications. Different
ControlIF could share the same Authenticator
* The *ControlClient* object, represent an application connected to the Server. Different ControlIF can share the same
type of ControlClient, or they can have their own.

The last object is the **ControlClient API object**. This objects works (and looks) very similar to other objects used
in PHP to connect to external services, like the MySqli object. So no further explanation is needed.
For an explanation on how to use it, refer to its own README.md.

## Basic Usage ##

First, if the packages were installed with composer, be careful to include the file <code>vendor/autoload.php</code>
at the beginning of your script.

```php
require("vendor/autoload.php"); /* taking for granted that vendor/ is into the include path */
```
The primary object we have to initialize, is the Server object. Just suppose we are okay with the standard one, so 
we can safely import the objects and use them directly
```php
use FF\Libs\PWA\WebSocket\Server\Server; 
use FF\Libs\PWA\WebSocket\Server\Websocket;

[...]

$server = new Server(websocket_class: Websocket::class);

$server->addr = "0.0.0.0";
$server->port = "9100";

$server->allowed_hosts = [
    "www.ffphp.com:9100",
    "localhost:9100",
];

$server->allowed_origins = [
    "http://localhost",
    "http://www.ffphp.com",
];
```
The parameters <code>addr</code> and <code>port</code> are the local ones to bind. Setting <code>addr</code> to
<code>0.0.0.0</code> will cause the server to accept
connections on any IP, private (like <code>127.0.0.1</code>, <code>192.168.1.1</code>, etc) and public.

The parameter <code>allowed_hosts</code> filter the incoming connections based on the domain name. This is useful when
a server is responding to multiple domain names with only one IP.

The parameter <code>allowed_origin</code> is the opposite one, it filters the incoming connections based on the origin
of the request.

Now we want to create a service with the client definition and add it to the server.
Some classes provided are suffixed as "_base". Those classes are not intended to be instantiated directly,
but to be extended in order to make the specific implementation. Of course that doesn't mean that the other classes
cannot be extended too.

The classes involved are "_base" ones, so we need to extend them.
```php
use FF\Libs\PWA\WebSocket\Server\Client_base;
use FF\Libs\PWA\WebSocket\Server\Service_base;

[...]

class myClient extends Client_base {

	function onOpen(): bool
	{
		return true;
	}

	function onError($code, $text)
	{
	}

	function onClose($code, $text)
	{
	}

	function onMessage($type, $payload)
	{
	}

	function getInfo(): array|null
	{
		return null;
	}

	function send(mixed $data): bool
	{
		return $this->websocket->sendText($data);
	}
}


class myService extends Service_base
{
	public function onNewClient($client):bool {
		return true;
	}

	public function onRemoveClient($client) {
	}
}
```
All the functions defined above are abstract functions that need to be defined. 

On the *Client* class, the **on** functions are a mirror of
the ones defined by the RFC on the client side, but from the server point of view.

As it can be seen, the <code>onOpen</code> function needs to return a <code>bool</code>. 
If it's <code>true</code>, the client will be accepted. Returning <code>false</code> cause the client 
to be disconnected.

the <code>getInfo</code> function is used to retrieve custom information about the client.
A good example could be returning the user id associated with the connection on our protocol implementation.

The <code>send</code> function is responsible for sending the data to the connected client. Websockets can send 
data of type text or binary, which type depends on our protocol implementation. In this case we are simply using 
the Websocket <code>sendText</code> function because we are going to realize a simple text exchange test.

On the *Service* class we could see the same behaviour described for the onOpen client function 
on the <code>onNewClient</code> function.

Now we need to add the service with his proper client to the server:
```php
$service = new myService(myClient::class);
$server->addService("the_only_service", $service);

$server->router->addRule("/", [
	"service" => "the_only_service"
]);
```
As it can be seen, after instancing the service, it needs to be added to the server passing a "name".
This name will be used by the router to match the path with the proper service.

The router is an object that maps source paths (the ones we use while connecting to the server)
with the services, and he does so using the name we provided before, as we can see in the example.
It accepts many params, but we want to stay simple for the sake of our example.

At this point, everything is set up for running our base server. So we start it adding the code:
```php
$server->start();
```
and that's enough. In order to test it just write <code>php -f scriptname.php</code> from the cli, 
and you will se the log from the server, something like this:
```text
Forms PHP Framework WebSocket Server v1.2.0
Copyright (c) 2021, Samuele Diella <samuele.diella@gmail.com>

2021-09-12 00:47:57.817352 INFO  Server                    Starting server..
2021-09-12 00:47:57.819436 INFO  Server                    Listening
```
To exit from it, just press <code>CTRL+C</code>.

If we want to test it, we could just open the file <code>test.html</code> provided in the repository 
under the path [tests/Libs/PWA/WebSocket/Server](/tests/Libs/PWA/WebSocket/Server/test.html).  
Remember that we didn't add any kind of code that send actual messages from the server
to the client, so the best you could do is to send message from the client to the server and see
the result in the log.

We can see the full code example in the file <code>basic_server.php</code> 
under [examples/Libs/PWA/WebSocket/Server](/examples/Libs/PWA/WebSocket/Server/basic_server.php).

## Advanced Usage ##

### Connections over SSL ###

The implementation supports the connection over SSL (and obviously this is the preferred type of connection).

In order to do that, first we need to allow the specific urls in the <code>allowed_origins</code> property of
the Server object
```php
$server->allowed_origins = [
	"https://localhost",
	"https://www.ffphp.com",
];
```
Second, we need to activate the SSL feature and set its options
```php
$server->ssl = true;

$server->ssl_options = [
  'cafile'              => "/my_certs_dir/your_ca.pem",
  'local_cert'          => "/my_certs_dir/your_domain.crt",
  'local_pk'            => "/my_certs_dir/your_domain.key",
  'allow_self_signed'   => true,
  'verify_peer'         => false,
];
```
as you can see, the options are the same of the php function <code>stream_context_create</code> that could be found at
the url https://www.php.net/manual/en/context.ssl.php.

If you are not using a third-party certificate, you want to create your own self-signed certificate,
and you don't know how to do, you can find a simple linux script under [tools/ssl](/tools/ssl/certs-gen.sh).
In order to use it, edit the script with your favorite text editor and change the options you see in the head.

### Returning Process Status ###

Being the server a standalone process, is often useful to return a value to the system, just in case some error happens.
If you install the script as a service with systemd, this is even more useful to help systemd understand if
something really happens.

The <code>start()</code> function of the server object returns <code>false</code> when the server couldn't be started
properly, <code>null</code> if the server is already started, <code>true</code> when it's started properly.

If an implementation error happens (some function used wrongly by the developer or an unmanaged error) 
an <code>Exception</code> will be thrown. All the other errors will be logged and stored in the proper object, then
they could be retrieved with the <code>getLastErrorCode()</code>, <code>getLastErrorString()</code> 
and <code>getLastErrorData()</code> functions.

So, in order to return the exit status to the system, we could wrap the code in a <code>try ... catch</code> structure and
return the proper code with the <code>exit</code> function
```php
try {

    [...]
    
    $server->start();
} catch (Exception $e) {
    exit($e->getCode());
}

exit($server->getLastErrorCode());
```

### Logs ###

This implementation uses the **Log** class from FF\Core\Common.
This class allow different classes on the same application to access shared error streams, that could be oriented 
both on consoles and files.

Through this class all the errors are formatted and grouped according to the severity and the context. Additional
data are passed as a reference.

The severity is classified accordingly to six standard levels and are codified through the FF
**\Core\Common\constLogLevels** abstract class.
1) <code>FATAL</code> the execution cannot continue, the server will exit
2) <code>ERROR</code> something so severe to usually cause at least a client to disconnect, not always leading to
the server stop.
3) <code>WARN</code> an event that needed to be watched, but nothing that could affect the server
4) <code>INFO</code> a normal event, useful to be recorded
5) <code>DEBUG</code> protocol information and useful data for debug purposes
6) <code>TRACE</code> everything is happening under the hood, even the sequence of function calling

It's possible to specify the level of the errors that must be put into the log for each kind of log managed by the
server. The higher levels will include also the lower levels. When setting the log-level, two additional levels
are provided
* <code>ALL</code> Everything will be included, no matter what
* <code>OFF</code> No errors are going to be displayed

In order to set the log level, first we need to get the log object. Every log object is identified by a name.
The log objects could be retrieved with the static function <code>Log::get()</code>. Additionally, all the objects
using the **Error** trait need to implement the <code>getLog()</code> function.

So, in order to set the log-level for the server object to <code>DEBUG</code>, we could do
```php
use FF\Core\Common\constLogLevels;

[...]

$server->getLog()->setLevel(constLogLevels::LOG_LEVEL_DEBUG);
```

By default, <code>STDIN</code> and <code>STDERR</code> will be used. If we want to log on a file, we could do
```php
$server->getLog()
    ->setOpt("to_file", true)
    ->setOpt("path", "/my_log_dir/server.log")
    ;
```

and if we want to split the error log, we could do
```php
$server->getLog()
    ->setOpt("to_file", true)
    ->setOpt("path", "/my_log_dir/server.log")
    ->setOpt("path_errors", "/my_log_dir/server-errors.log")*/
    ;
```

The implementation allows to have different logs for every significant class. The names are all specified in the 
<code>log_*</code> properties of the server object. By default, all the logs are on the same destination.

Just suppose we want to split the Control Interfaces logs (we don't have defined a ControlIF yet, it's just an example)
on a different destination. We could do
```php
$server->log_control_if         = "control_log";
$server->log_control_clients    = "control_log";
```

And then set all the options
```php
use FF\Core\Common\Log;

[...]

Log::get("control_log")
	->setOpt("to_file", true)
	->setOpt("path", "/my_log_dir/control.log")
	->setOpt("path_errors", "/my_log_dir/control-errors.log");
```

Overloading the <code>getLog()</code> function in the custom classes allow specifying even more different logs.

If we want to suppress the copyright info on the console for a totally silent execution, we could do that 
through the <code>start</code> function
```php
$server->start(daemonize: true);
```

### Keeping the clients alive ###

The WebSocket implementation provides a *ping/pong* mechanism. On the implementation this could be set through 
the service class. In this way all the clients belonging to different services could respond to different rules.

For example, just imagine to desire to check if a client is still alive after 5 minutes of inactivity
```php
$service->ping_interval_mins = 5;
```
So after 5 minutes without receiving any kind of messages, a *ping* will be sent, and so every 5 minutes since then.
If the client will not answer with a *pong* message, it will be disconnected.

If you want to put a limit on the total time a client is inactive, no matter if it responds to the *pings*
```php
$service->ping_max_before_dc = 3;
```

This option will set the maximum pings that could be sent to three before disconnecting the person.
So after <code>5 minutes * 3 + 5 minutes_until_the_fourth = 20 minutes</code>, the inactive client will be disconnected.

### Control Interfaces ###

If we want to manage our server from a web application, we need an interface to do it. Two interfaces are provided with
this implementation
* <code>ControlInterface_unixsock</code> for connection on server running on the same machine
* <code>ControlInterface_tcp</code> for connection on processes running on a different machine over the network
  (nothing forbids to use it on the same machine though)

Every ControlIF needs to use an <code>Authenticator</code> instance. With the implementation a class named
<code>Authenticator_base</code> is provided. It allows defining (at runtime) pairs of id/secret (username/password).

Every application connecting to the Control Interface will be managed as a "ControlClient". In this case, only the
"_base" version is provided, so we need to extend it.

So a minimal implementation would be:
```php
use FF\Libs\PWA\WebSocket\Server\ControlClient_base;
use FF\Libs\PWA\WebSocket\Server\Authenticator_simple;
use FF\Libs\PWA\WebSocket\Server\ControlInterface_unixsock;

[...]

$control = new ControlInterface_unixsock(new Authenticator_simple(), myControlClient::class);
$server->addControlIF($control);
```
By default, the UNIX sock will be created under <code>sys_get_temp_dir()</code>. If we want to customize it we could
use the <code>path</code> and <code>name</code> properties.

The control client supports the data encryption through a custom certificate. This is not to be confused with the
certificate used for the websocket connection. The server will use the private certificate,
the API will use the public one.
```php
$control->encryption = true;
$control->enc_private_key = "/my_keys_dir/key-priv.crt";
```
If you have trouble in creating the pairs, you could use the script <code>key-gen.php</code> 
under [tools/ssl](/tools/ssl/key-gen.php)

If you want to see a full version of the Advanced Usage, watch <code>advanced_server.php</code> 
under [examples/Libs/PWA/WebSocket/Server](/examples/Libs/PWA/WebSocket/Server/advanced_server.php).

The <code>ControlInterface_tcp</code> class holds the same properties for the connection as the server does. So it will have
<code>addr</code>, <code>port</code>, <code>ssl</code> and <code>ssl_options</code>.
It have the *encryption* params, but if you are using a SSL connection they are useless.

As stated before, for the **ControlClient API object**, refer to its own README.md.
