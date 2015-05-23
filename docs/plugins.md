PSB Plugins
===========

[Back to documentation](../README.md#documentation)

Plugins expand a message bus with additional functionality. The basic task of a message bus, be it a [CommandBus](command_bus.md) or [EventBus](event_bus.md),
is to dispatch a message. To achieve this goal the bus needs to collect some information about the message and perform
actions to ensure that a responsible message handler is invoked. Detailed information about the process can be found on the appropriate bus documentation pages.
Plugins hook into the dispatch process and provide the required information like the name of the message or a routing map and they also
prepare the message for invocation, locate the message handlers and invoke them.
PSB ships with a list of useful plugins that can be mixed and matched with your own implementations:

# Routers

## Prooph\ServiceBus\Router\CommandRouter

Use the CommandRouter to provide a list of commands (identified by their names) and their responsible command handlers.

```php
//You can provide the list as an associative array in the constructor ...
$router = new CommandRouter(array('My.Command.BuyArticle' => new BuyArticleHandler()));

//... or using the programmatic api
$router->route('My.Command.RegisterUser')->to(new RegisterUserHandler());

//Command handlers can be objects like shown above or everything that is callable (callbacks, callable arrays, etc.) ...
$router->route('My.Command.SendPaymentEmail')->to(array($mailer, "handleSendPaymentEmail"));

//... or a string that can be used by a DIC to locate the command handler instance
$router->route('My.Command.PayOrder')->to("payment_processor");

//Add the router to a CommandBus
$commandBus->utilize($router);
```

## Prooph\ServiceBus\Router\QueryRouter

Use the QueryRouter to provide a list of queries (identified by their names) and their responsible finders.

The QueryRouter share the same base class with the CommandRouter so its interface looks exactly the same.


## Prooph\ServiceBus\Router\EventRouter

Use the EventRouter to provide a list of event messages (identified by their names) and all interested listeners per event message.

```php
//You can provide the list as an associative array in the constructor ...
$router = new EventRouter(array('My.Event.ArticleWasBought' => array(new OrderCartUpdater(), new InventoryUpdater())));

//... or using the programmatic api
$router->route('My.Event.ArticleWasBought')->to(new OrderCartUpdater())->andTo(new InventoryUpdater());

//Like command handlers, event message listeners can also be objects, callables or strings
$router->route('My.Event.OrderWasPayed')->to("delivery_processor");

//Add the router to an EventBus
$eventBus->utilize($router);
```

## Prooph\ServiceBus\Router\RegexRouter

The RegexRouter works with regular expressions to determine handlers for messages. It can be used together with a CommandBus, a QueryBus and
an EventBus but for the latter it behaves a bit different. When routing a command or query the RegexRouter makes sure that only one pattern matches.
If more than one pattern matches it throws a `Prooph\ServiceBus\Exception\RuntimeException`. On the other hand when routing
an event each time a pattern matches the corresponding listener is added to the list of listeners.

```php
//You can provide the pattern list as an associative array in the constructor ...
$router = new RegexRouter(array('/^My\.Command\.Buy.*/' => new BuyArticleHandler()));

//... or using the programmatic api
$router->route('/^My\.Command\.Register.*/')->to(new RegisterUserHandler());

//Add the router to a CommandBus
$commandBus->utilize($router);

//When routing an event you can provide a list of listeners for each pattern ...
$router = new RegexRouter(array('/^My\.Event\.Article.*/' => array(new OrderCartUpdater(), new InventoryUpdater())));

//... or using the programmatic api
$router->route('/^My\.Event\.Article.*/')->to(new OrderCartUpdater());

//The RegexRouter does not provide a andTo method like the EventRouter.
//You need to call route again for the same pattern,
//otherwise the router throws an exception
$router->route('/^My\.Event\.Article.*/')->to(new InventoryUpdater());

//Add the router to an EventBus
$eventBus->utilize($router);
```

# Invoke Strategies

An invoke strategy knows how a message handler can be invoked. You can register many invoke strategies at once depending on
how many different handlers you are using. The best way is to choose a convention and go with it. PSB ships with the invoke strategies
listed below. If your favorite convention is not there you can easily write your own invoke strategy
by extending the [AbstractInvokeStrategy](../src/Prooph/ServiceBus/InvokeStrategy/AbstractInvokeStrategy.php) and implementing the
`canInvoke` and `invoke` methods.

## Available Strategies

- `CallbackStrategy`: Is responsible for invoking callable message handlers, can be used together with a CommandBus and EventBus
- `HandleCommandStrategy`: Is responsible for invoking a `handle` method of a command handler. Forces the rule that a command handler should only be responsible for handling one specific command.
- `OnEventStrategy`: Prefixes the short class name of an event with `on`. A listener should
have a public method named this way: OrderCartUpdater::onArticleWasBought.
- `FinderInvokeStrategy`: This strategy is responsible for invoking finders. It either looks for a finder method named like the short class name of the query or it
checks if the finder is callable (implements the magic __invoke method f.e.).
- `ForwardToRemoteMessageDispatcherStrategy`: This is a special invoke strategy that is capable of translating a command or event to
a [RemoteMessage](https://github.com/prooph/common/blob/master/src/Messaging/RemoteMessage.php) and invoke a [RemoteMessageDispatcher](message_dispatcher.md).
Add this strategy to a bus together with a [ToRemoteMessageTranslator](../src/Prooph/ServiceBus/Message/ToRemoteMessageTranslator.php) and
route a command or event to a RemoteMessageDispatcher to process the message async:

```php
$eventBus->utilize(new ForwardToRemoteMessageDispatcherStrategy(new ProophDomainMessageToRemoteMessageTranslator()));

$router = new EventRouter();

$router->route('SomethingDone')->to(new My\Async\MessageDispatcher());

$eventBus->utilize($router);

$eventBus->dispatch(new SomethingDone());
```

- `ForwardToRemoteQueryDispatcherStrategy`: Like the `ForwardToRemoteMessageDispatcherStrategy` this invoke strategy translates a
query to a [RemoteMessage](https://github.com/prooph/common/blob/master/src/Messaging/RemoteMessage.php) but it invokes a [RemoteQueryDispatcher](message_dispatcher.md#RemoteQueryDispatcher) instead.


# FromRemoteMessageTranslator

The [FromRemoteMessageTranslator](../src/Prooph/ServiceBus/Message/FromRemoteMessageTranslator.php) plugin does the opposite of the `ForwardToRemoteMessageDispatcherStrategy`.
It listens on the `initialize` dispatch action event of a CommandBus, QueryBus or EventBus and if it detects an incoming [RemoteMessage](https://github.com/prooph/common/blob/master/src/Messaging/RemoteMessage.php)
it translates the message to a [Command](https://github.com/prooph/common/blob/master/src/Messaging/Command.php), [Query](https://github.com/prooph/common/blob/master/src/Messaging/Query.php)  or [DomainEvent](https://github.com/prooph/common/blob/master/src/Messaging/DomainEvent.php) depending on the type
provided in the [MessageHeader](https://github.com/prooph/common/blob/master/src/Messaging/MessageHeader.php). A receiver of an asynchronous dispatched message, for example a worker of a
message queue, can pull a [RemoteMessage](https://github.com/prooph/common/blob/master/src/Messaging/RemoteMessage.php) from the queue and forward it to a appropriate configured CommandBus or EventBus without additional work.

*Note: If the message name is an existing class it is used instead of the default implementation.
       But the custom message class MUST provide a static `fromRemoteMessage` factory method, otherwise the translator will break with a fatal error!

# ServiceLocatorProxy

This plugin uses a Prooph\Common\ServiceLocator implementation to lazy instantiate command handlers and event listeners.
The following example uses a ZF2 ServiceManager as a DIC and illustrates how it can be used together with a command bus:

```php
use Zend\ServiceManager\ServiceManager;
use Prooph\Common\ServiceLocator\ZF2\ZF2ServiceManagerProxy;

//We tell the ServiceManager that it should provide an instance of My\Command\DoSomethingHandler
//when we request it with the alias My.Command.DoSomethingHandler
$serviceManager = new ServiceManager(new Config([
    'invokables' => [
        'My.Command.DoSomethingHandler' => 'My\Command\DoSomethingHandler'
    ]
]));

//The ZF2ServiceManagerProxy implements Prooph\Common\ServiceLocator
$commandBus->utilize(new ServiceLocatorProxy(ZF2ServiceManagerProxy::proxy($serviceManager)));

$router = new CommandRouter();

//In the routing map we use the alias of the command handler
$router->route('My.Command.DoSomething')->to('My.Command.DoSomethingHandler');

$commandBus->utilize($router);
```

With this technique you can configure the routing for all your messages without the need to create all the message handlers
on every request. Only the responsible command handler or all interested event listeners (when dealing with event messages)
are lazy loaded by the ServiceManager. If you prefer to use another DIC then write your own proxy which implements Prooph\Common\ServiceLocator.