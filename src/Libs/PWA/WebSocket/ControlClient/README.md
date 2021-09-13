# FF - Forms PHP Framework #

## WebSocket Server: Control Client API object ##

Considering that Websocket servers are intended to run as standalone processes, so not through a normal request
to a http server like apache, an API is needed in order to connect and communicate with it (control it) from
outside. For the FF implementation, this object-oriented API is provided.

For who is familiar with PHP API objects like MySQLi, this looks very similar, so it will be not hard to use it.

For more basic concept about the Server is structured and managed, please read the provided
[README.md](/src/Libs/PWA/WebSocket/Server/README.md)

### Usage ###

Each connection is represented by a *ControlClient* object. There are two object already provided, extended from
the <code>ControlClient_base</code> object
* <code>ControlClient_unixsock</code> for connecting to process running on the same machine
* <code>ControlClient_tcp</code> for connection to process running on different machines (though nothing forbid using
it for connecting on the same)

In order to use one of these objects, the Server needs to implement the corresponding Control Interface.

First, the desired object needs to be instantiated. Let's say we are using the unixsock version
```php
use FF\Libs\PWA\WebSocket\ControlClient\ControlClient_unixsock;

require "../vendor/autoload.php";

$conn = new ControlClient_unixsock();
```

Now we need to explicitly call the <code>connect()</code> method to connect to the server.
```php
$conn->connect();
```

By default, the implementation will look for the socket in the same <code>ControlInterface_unixsock</code> 
default directory. If we changed the socket name or location there, we could pass the new path to the <code>path</code>
parameter of the connect function.

After connecting, the first thing we need to do is to authenticate ourselves. In order to do that, we need to 
explicitly call the <code>auth</code> method. The ControlIF protocol requires that the first command we send
is the <code>auth</code> one, otherwise we'll get disconnected.

The <code>auth</code> method require an array of credentials. The kind of credentials passed the the function depends on the Authenticator used
on the Server. Let's assume we used the default <code>Authenticator_simple</code> object, with the default credential
provided
```php
$conn->auth([
	"id"        => "admin",
	"secret"    => "password" /* yes, super safe! */
]);
```

After the connection we have to make a choice. We could work with all clients and the services presents on the server,
or we could select a service and limit our commands to the clients connected to that specific services.
The kind of services we could use with the user we provided depends on how is permissions are set on the server.

Let's assume we have a service named "the_only_service" (as in our Server example) and we want to work with that.
```php
$conn->selectServiceByName("the_only_service");
```

Or, if we prefer, we could use the path like the Websocket clients does
```php
$conn->selectServiceByPath("/");
```

Now we are ready to send commands to the server. For Instance, we could get the clients list
```php
$clients = $conn->listClients();
```

And we could send a message to all the clients retrieved
```php
$conn->sendMessage("I can see you...", array_keys($clients));
```

When we have finished our business, we could free the connection-
```php
$conn->disconnect();
```

And that's it!

### Advanced Usage ###

#### Error handling #####

Every method that will fail will return <code>false</code>, so it's wise to check for it every time some method
is called. In order to get further information about the error, it's possible to use the <code>Core\Common\Errors</code>
methods.

If we apply this principle to the code above, about retrieving the client list, it would be

```php
$clients = $conn->listClients();
if ($clients === false)
    throw new \Exception(
        message: "Unable to retrieve client list: " . $conn->getLastErrorString(),
        code: $conn->getLastErrorCode()
    );
```

#### Data Encryption #####

The Control API supports the data encryption through SSL private/public keys. In order to work, it must be activated
on both the server and the API.

For the API part, it's done this way
```php
$rc = $conn->setEncryption(
	enable: true,
	publickey: "file://my_keys_dir/key-pub.crt"
);
```
The <code>publickey</code> param accept the same params as the <code>openssl_pkey_get_public</code> PHP API does.

The full code example is in the file <code>send_message.php</code>
under [examples/Libs/PWA/WebSocket/ControlClient](/examples/Libs/PWA/WebSocket/ControlClient/send_message.php).
