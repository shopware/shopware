[titleEn]: <>(Message Queue)
[hash]: <>(article:message-queue)

Shopware integrates with the [Symfony Messenger](https://symfony.com/doc/current/components/messenger.html) component 
and [Enqueue](https://enqueue.forma-pro.com/). This gives you the possibility to send and handle asynchonous messages.

## Components

### Message Bus

The [message bus](https://symfony.com/doc/current/components/messenger.html#bus) is used to dispatch your messages to your registered handlers.
While dispatching your message it loops through the configured middleware for that bus. The message bus used inside shopware can be found under the service tag `messenger.bus.shopware`.
It is mandatory to use this message bus if your messages should be handled inside shopware. However if you want to send messages to external systems you can define your custom message bus for that.

### Middleware

A [middleware](https://symfony.com/doc/current/messenger.html#middleware) is called when the message bus dispatches messages. 
The middleware defines what happens when you dispatch a message. For example the `send_message` middleware is responsible for sending
your message to the configured transport and the `handle_message` middleware will actually call your handlers for the given message.
You can add your own middleware by implementing the `MiddlewareInterface` and adding that middleware to the message bus through configuration.

### Handler

A [handler](https://symfony.com/doc/current/messenger.html#registering-handlers) gets called once the message is dispatched by the `handle_messages` middleware.
Handlers do the actual processing of the message, therefore they must extend the `AbstractHandler`-class and implement the `handle()`-method.
To register a handler you have to tag it with the `messenger.message_handler` tag.
To specify which methods should be handled by a given handler implement the static `getHandledMessages()`-method and return the MessageClasses that handler should handle.
You can also define multiple handlers for the same message.

### Message

A [message](https://symfony.com/doc/current/messenger.html#message) is a simple PHP class that you want to dispatch over the MessageQueue.
It must be serializable and should contain all necessary information that your handlers need to process the message.

### Envelope

A message will be wrapped in [envelope](https://symfony.com/doc/current/components/messenger.html#adding-metadata-to-messages-envelopes) by the message bus that dispatches the message.

### Stamps

While the message bus is processing the message through it's middleware it adds [stamps](https://symfony.com/doc/current/components/messenger.html#adding-metadata-to-messages-envelopes) to the envelope that contain metadata about the message.
If you need to add metadata or configuration to your message you can either wrap your message in an Envelope and adding the necessary stamps before dispatching your message or 
you can create your own custom middleware for that.

### Transport

A [Transport](https://symfony.com/doc/current/messenger.html#transports) is responsible for communicating with your 3rd party message broker.
You can configure multiple Transports and route messages to multiple or different Transports. 
Supported are all Transports that are either supported by [Symfony](https://symfony.com/doc/current/messenger.html#transports) itself, or by [Enqueue](https://github.com/php-enqueue/enqueue-dev/tree/master/docs/transport).
If you don't configure a Transport messages will be processed synchronously like in the Symfony event system.

## Sending Messages

To send a message you simply inject the message bus into your service.
```xml
<!-- services.xml -->
<services>
    <service id="App\MessageSender\MySender">
       <argument type="service" name="messenger.bus.shopware" />
   </service>
</services>
```
Inside your service you simply create the message you want to sent and dispatch it to the message bus.
```php
<?php

use \Symfony\Component\Messenger\MessageBusInterface;

class MySender
{
    /**
     * @var MessageBusInterface
     */
    private $bus;
    
    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }
    
    public function action()
    {
        $this->bus->dispatch(new SmsNotification('A string to be sent...'));
    }
}
```
If you want to add metadata to your message you can simply dispatch an envelope with the necessary stamps.
```php
<?php

use \Symfony\Component\Messenger\MessageBusInterface;

class MySender
{
    /**
     * @var MessageBusInterface
     */
    private $bus;
    
    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }
    
    public function action()
    {
        $message = new SmsNotification('A string to be sent...');
        $this->bus->dispatch(
            (new Envelope($message))
            ->with(new SerializerStamp())
        );
    }
}
```

### Encrypted Messages

As the sent messages may travel through some 3rd party services you may want to encrypt messages containing sensible information.
To send encrypted messages simply use the `encrypted.messenger.bus.shopware` rather than the `messenger.bus.shopware` message bus.
The encrypted bus will handle encryption and decryption for you.
```xml
<!-- services.xml -->
<services>
    <service id="App\MessageSender\MySender">
       <argument type="service" name="encrypted.messenger.bus.shopware" />
   </service>
</services>
```

## Handling Messages

Simply create a service an tag it with the `messenger.message_handler` tag.
```xml
<!-- services.xml -->
<services>
    <service id="App\MessageHandler\MyHandler">
       <tag name="messenger.message_handler" />
    </service>
</services>
```
In your handler extend the `AbstractHandler` and implement the necessary methods.
```php
<?php

use \Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;

class MyHandler extends AbstractMessageHandler
{
        /**
         * @param SmsNotification $message
         */
        public function handle($message): void
        {
            //handle your message
        }
    
        public static function getHandledMessages(): iterable
        {
            return [SmsNotification::class];
        }
}
```

## Consuming Messages

There is a `console` command to start a worker that will receive incoming messages from your transport and dispatch them.
Simply start the worker with `bin\console messenger:consume-messages default`, where `default` is the transport you want to consume messages from.
There is also an API-Route that let you consume messages for a given transport. 
Just post to the route `/api/v3/_action/message-queue/consume` and define the transport from which you want to consume messages as the receiver in the requests body:
```json
{
  "receiver": "default",
}
```

The receiver will consume messages for 2 seconds and then you get the count of the handled messages in the response:
```json
{
  "handledMessages": 15
}
```

### The admin-worker

Per default there is an admin-worker that will periodically ping the consume messages endpoint from the administration. 
This feature is intended for development and hosting environments in which a more complex setup is not possible.
However you really should use the cli-Worker in production setups, because the Admin-worker just consumes messages if an administration user is logged in.

### The cli-worker

The recommended way to consume messages is through the cli command. 
You can configure the command just to run a certain amount of time or to stop if it exceeds a certain memory limit like:
```bash
bin/console messenger:consume-messages default --time-limit=60
```

```bash
bin/console messenger:consume-messages default --memory-limit=128M
```

For more information about the command and its configuration use the `-h` option:
```bash
bin/console messenger:consume-messages -h
```

You should use the limit option to periodically restart the worker processes, because of the memory leak issues of long running php processes.
To automatically start the processes again after they stopped because of exceeding the given limits you can use something like [upstart](http://upstart.ubuntu.com/getting-started.html) or [supervisor](http://supervisord.org/running.html).
Alternatively you can configure a `CronJob` that runs the command again shortly after the time limit is exceeded.

If you have configured the cli-worker, you can turn off the admin worker in your `shopware.yaml`.
```yaml
# config/packages/shopware.yaml
shopware:
    admin_worker:
        enable_admin_worker: false
``` 
**Note:** This will disable the AdminWorker completely and you have to configure the cli-worker for scheduled tasks as well.

## Configuration

### Message Bus

You can configure a array of buses and define one default bus in your `framework.yaml`.
```yaml
# config/packages/framework.yaml
framework:
    messenger:
        default_bus: messenger.bus.shopware
        buses:
            messenger.bus.shopware:
``` 

For more information on this check the [symfony docs](https://symfony.com/doc/current/messenger/multiple_buses.html).

### Middleware

For each defined bus in your `framework.yaml` you can define the middleware that this bus should use.
To add middleware simply specify your custom middleware like this:
```yaml
# config/packages/framework.yaml
framework:
    messenger:
        default_bus: messenger.bus.shopware
        buses:
            messenger.bus.shopware:
              middleware:
                - 'App\Middleware\MyMiddleware'
                - 'App\Middleware\AnotherMiddleware'
``` 
Per default every message bus uses following middleware in that order:
1. `logging` middleware: for logging
2. your custom middleware
3. `send_message` middleware: for sending the message to the transport
4. `handle_message` middleware: for calling all registered handlers for the given message

For more information on this check the [symfony docs](https://symfony.com/doc/current/messenger.html#middleware).

### Transport
You can configure an amqp transport directly in your `framework.yaml`, however as Shopware integrates with Enqueue it is best practice 
to configure your transport in the `enqueue.yaml` and simply tell symfony to use your enqueue transports. 
In your `enqueue.yaml` simply configure your transports like this:
```yaml
# config/packages/enqueue.yaml
enqueue:
  default: # the name of your transport
    transport: ~ # the transport configuration
    client: ~ # the client configuration
```
In a simple setup you only need to set the transport to a valid DSN like:
```yaml
# config/packages/enqueue.yaml
enqueue:
  default: 
    transport: "file://path/to/my/file"
    client: ~
```
Notice that for different schemas (e.g. `file://`, `amqp://`) different enqueue transports are required. 
Shopware just ships with the Filesystem transport, so you need to require the transport you want to use. 
A list of all transports supported by enqueue can be found [here](https://github.com/php-enqueue/enqueue-dev/tree/master/docs/transport).

For more information on this check the [enqueue docs](https://github.com/php-enqueue/enqueue-dev/blob/master/docs/bundle/config_reference.md).

To tell symfony to use your transports from your `enqueue.yaml` simply use:
```yaml
# config/packages/framework.yaml
framework:
    messenger:
        transports:
          default: # the name for the transport used by symfony
            enqueue://default # 'enqueue://' followed by the name of your transport used in 'enqueue.yaml'
``` 

### Routing

You can route messages to different Transports, just configure your routing in the `framework.yaml`.
```yaml
# config/packages/framework.yaml
framework:
    messenger:
        transports:
          default: enqueue://default
          another_transport: enqueue://another_transport
        routing: 
          'MyApp\Message\SmsNotification': another_transport
          'MyApp\Message\EmailNotification': [default, another_transport]
          '*': default
``` 

You can route messages by their classname and use the asterisk as a fallback for all other messages.
If you specify a list of transports the messages will be routed to all of them. 
For more information on this check the [symfony docs](https://symfony.com/doc/current/messenger.html#routing).

### Admin worker

The admin-worker can be configured/disabled in the general shopware.yml configuration.
If you want to use the admin worker you have specify each transport, that previously was configured.
The poll interval is the time in seconds that the admin-worker polls messages from the queue. After the poll-interval is over the request terminates and the administration initiates a new request.
```yaml
# config/packages/shopware.yaml
shopware:
    admin_worker:
        enable_admin_worker: true
        poll_interval: 30
        transports: ["default"]
``` 
