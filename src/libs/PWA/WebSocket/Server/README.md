# FF - Forms PHP Framework #

## WebSocket Server ##

This is the FF implementation of a multi-purpose WebSocket server.  

Aside from the basic Websocket protocol, that is to be considered and "underlying" protocol 
used just for the connection management and the data transmission, 
this implementation doesn't provide any kind of protocol implementation.
So it doesn't manage message types aside from the ones embedded in the websocket protocol,
id doesn't manage user authentication or authorization to services (it doesn't manage users at all),
and so on.  
For this reason, it's a perfect basis for developing various websocket implementation,
like chat servers, notification servers and so on.  

Taken that the servers are intended to run as standalone processes, together with the server
a Control Client is provided, ready to be embedded in any kind of web applications
(even not based on FF). Basically it's an object-oriented API able to connect and communicate with
the Websocket implementations for listing the clients, sending messages and so on, all within a
normal PHP app.  

### Basic concepts ###

When in the code and in the documentation we are talking about a "Websocket **server**", 
we are referring to a php script running from the CLI (or started as a service)
that open a network port and listen for incoming connection from browsers (usually).  

In the same session we could of course start various servers listening on different ports,
but that's not useful because following the Websocket protocol RFC 
[#6455](https://datatracker.ietf.org/doc/html/rfc6455) guidelines, 
this implementation is able to manage different "**services**" on the same server.  
This is helpful because in a production environment it couldn't be so easy to have
as many ports as we want, and in this way if we want we can establish a communication between
services.  

One example could be the one of a web application that offers a chat for the users.  
The chat itself is a service, with his own specific protocol.  
Now just imagine we want a real-time notification of what is happening in the chat
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
In order to differentiate the two services, we could use two different urls, like:
* <code> wss://www.php.com:9100/chat </code> for the chat
* <code> wss://www.php.com:9100/admin </code> for the notifications

Internally, the server will use a routing mechanism to recognize the two different paths and
to attach the connection to the proper service.  

When a client (like the javascript from a browser) connect to the server, the connection passes through two stages.  

1) The handshake. In this phase an "agnostic" **Websocket** object is created to manage the single connection.  
In this phase things like the protocol version are checked, and all the data needed to understand the service are 
recognized.
2) The service routing. After the proper service is detected, a **Client** object specific to that service is created 
and attached to the service. Since that moment, the Client will be effectively watched for data transfers.

So far, we have described *four* of the main objects used in this implementation:
* the **Server** object, manager of everything related to the connections. It opens a port and listen for the 
clients to connect.
* the **Service** objects, actual placeholders of the code related to specific functionalities.
* the **Websocket** objects, manager of the single connection to a client
* the **Client** objects, specific to the service used by the single connection, each one of em holds  
a Websocket object

The last players in this scenario are the *Control objects*. This is something that is not described by the RFC,
but provided in order to make the software manageable.  

Being the Server as we said a standalone running process, it's not directly connected to any web application,
that usually are provided under a web server like Apache. But it could be useful to manage the server from 
a normal application.  
Following our chat example, we could want to disconnect instantly any connected user when his account is deleted.
Or we could want to broadcast a message to all the clients when an action happen in the data entry, like a new post
is made. Actually this could be used to make the clients connected to a data-entry app instantly notified of what
is happening. Just imagine: two users are connected to the same data-entry page. One of them made a change to the
data and the other is instantly notified that the data are changed, and if possible the data could be refreshed even
instantly without the need of any user interaction or polling mechanism. And so on.

In order to do that, the application need an API to communicate to the server, and the server need an interface
for the api that is aside from the normal connection to the websocket.  

Those two concept are managed by the "**ControlIF**" and "**ControlClient**" object.  

The ControlIF (Control Interface) is the object listening on the Websocket Server. The ControlClient is the API object
used by the application to connect to the Websocket Server.

One server could use many ControlIF. The need of that could be we want to have different ways to connect to the same
Server, for instance through a TCP port and through a unix sock.

Each ControlIF need to use an **Authenticator**. There is a simple authenticator provided with the framework,
based on classic id/secret (username/password) pairs. But any kind of authenticator could be made: based on OAuth,
on the system users, on a DB table and so on. Different ControlIF can share the same Authenticator.  
The Authenticator will be responsible not only for the authentication process, but also for the authorization on the
services. That is useful if we want to give permission to some apps to access only one service instead of every service
present in the server.

Each time a connection between an application and the ControlIF is made, a **ControlClient** object is created into the
Server. This object will hold everything is needed to manage the connection with the application, and it must not be
confused for the ControlClient API object, that lies in a totally different package, and it's solely used by 
the applications trying to connect to the server. Different ControlIF can customize the type of ControlClient they want.

So, we have described the last *three* server objects:
* The **ControlIF** object, allow and manage external applications to connect to the Server. One Websocket Server could
have multiple ControlIF running
* The **Authenticator** object, it's used by the ControlIF to authenticate and authorize the applications. Different
ControlIF could share the same Authenticator
* The **ControlClient** object, represent an application connected to the Server. Different ControlIF can share the same
type of ControlClient, or they can have their own.

The last object is the **ControlClient API object**. This objects works (and looks) very similar to other objects used
in PHP to connect to external services, like the MySqli object. So no further explanation is needed.
By default, the framework supports two kind of connection to the server: via a TCP connection and via a unix sock, 
so both version of the ControlClient API object are provided.

## Basic Usage ##

Many of the classes provided are suffixed as "_base". Those classes are not intended to be instantiated directly,
but to be extended in order to make the specific implementation. Of course that doesn't mean that the other classes
cannot be extended too.  

So, for instance, in order to use the Client class, the code could be this way:
```php
use \FF\Libs\PWA\WebSocket\Server;

class myClient extends Server\Client_base {
    ...
} 
```
All the needed objects need to be extended and/or instantiated and/or passed as params to the containing objects.

Let's see a basic example.

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
The parameters *addr* and *port* are the local ones to bind. Setting *addr* to 0.0.0.0 will cause the server to accept
connections on any IP, private (like 127.0.0.1, 192.168.1.1, etc) and public.

The parameter *allowed_hosts* filter the incoming connections based on the domain name. This is useful when
a server is responding to multiple domain names with only one IP.

The parameter *allowed_origin* is the opposite one, it filter the incoming connections based on the origin
of the request.

Now we want to create a service with the client definition and add it to the server.
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
All the function defined above are abstract function that needed to be defined. The **on** functions are a mirror of
the ones defined by the RFC on the client side, but obviously here are regarding the server.

As it can be seen, the **onOpen** function needs to return a bool. 
If it's true, the client will be accepted. Returning false cause the client to be disconnected.

the **getInfo** function is used to retrieve custom information about the client.
A good example could be returning the user id associated with the connection on our protocol implementation.

The **send** function is responsible for sending the data to the connected client. Websockets can send data of type
text or binary, which type depends on our protocol implementation. In this case we are simply using the Websocket
**sendText** function because we are going to realize a simple text exchange test.

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

The router is an object used by the server that maps source paths (the ones we use while connecting to the socket)
to the services, and he does so using the name we provided before, as we can see in the example. It accept many
params, but we want to stay simple for the sake of our example.

At this point, everything is set for running our base server. So we start it adding the code:
```php
$server->start();
```
and that's enough. In order to test it just write php -f scriptname.php, and you will se the log from the server, 
something like that:
```text
Forms PHP Framework WebSocket Server v1.2.0
Copyright (c) 2021, Samuele Diella <samuele.diella@gmail.com>

2021-09-12 00:47:57.817352 INFO  Server                    Starting server..
2021-09-12 00:47:57.819436 INFO  Server                    Listening
```
To exit from it, just press CTRL+C.