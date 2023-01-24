#Chief

[![Build Status](https://scrutinizer-ci.com/g/adamnicholson/Chief/badges/build.png?b=master)](https://scrutinizer-ci.com/g/adamnicholson/Chief/build-status/master) [![Code Coverage](https://scrutinizer-ci.com/g/adamnicholson/Chief/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/adamnicholson/Chief/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/adamnicholson/Chief/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/adamnicholson/Chief/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/2459f377-6af7-43b8-98df-c67f42138080/mini.png)](https://insight.sensiolabs.com/projects/2459f377-6af7-43b8-98df-c67f42138080)

Chief is a powerful standalone command bus package for PHP 5.4+.

## Contents

- [What is a command bus](#command-bus)
- [Installation](#installation)
- [Usage](#usage)
    - [Class-based command handlers](#automatic-handler-resolution)
    - [Anonymous functions as command handlers](#handlers-as-anonymous-functions)
    - [Self-handling commands](#self-handling-commands)
    - [Decorators](#decorators)
    - [Queued commands](#queued-commands)
    - [Transactional commands](#transactional-commands)
- [Dependcy injection container integration](#dependency-injection-container-integration)
- [License](#license)
- [Contributing](#contributing)
- [Author](#author)

## Command Bus?

> The most common style of interface to a module is to use procedures, or object methods. So if you want a module to calculate a bunch of charges for a contract, you might have a BillingService class with a method for doing the calculation, calling it like this `$billingService->calculateCharges($contract);`. A command oriented interface would have a command class for each operation, and be called with something like this `$cmd = new CalculateChargesCommand($contract); $cmd->execute();`. Essentially you have one command class for each method that you would have in the method-oriented interface. A common variation is to have a separate command executor object that actually does the running of the command. `$command = new CalculateChargesCommand($contract); $commandBus->execute($command);`

-- From [Martin Fowler's Blog](http://martinfowler.com/bliki/CommandOrientedInterface.html) (*code samples haven ported to PHP*):

That 'executor' Martin mentions is what we call the command bus. The pattern typically consists of 3 classes:

1. `Command`: A tiny object containing some data (probably just some public properties or getters/setters)
2. `CommandHandler`: Responsible for running the command through a `handle($command)` method
3. `CommandBus`: All commands are passed to the bus `execute($command)` method, which is responsible for finding the right `CommandHandler` and calling the `handle($command)` method.

For every `Command` in your application, there should be a corresponding `CommandHandler`.

In the below example, we demonstrate how a command bus design could handle registering a new user in your system using Chief:

```php
use Chief\Chief, Chief\Command;

class RegisterUserCommand implements Command {
	public $email;
	public $name;
}

class RegisterUserCommandHandler {
	public function handle(RegisterUserCommand $command) {
		Users::create([
			'email' => $command->email,
			'name' => $command->name
		]);
		Mailer::sendWelcomeEmail($command->email);
	}
}

$chief = new Chief;

$registerUserCommand = new RegisterUserCommand;
$registerUserCommand->email = 'adamnicholson10@gmail.com';
$registerUserCommand->name = 'Adam Nicholson';

$chief->execute($registerUserCommand);
```


## Installation

Install the latest version with `composer require chief/chief`, or see [Packagist](https://packagist.org/packages/adamnicholson/chief).

No further setup is required, however if you're using a framework and want to make sure that we play nicely (with DI Containers, Event handlers, etc), then use the bridges below.

#### Laravel

After installing via composer, add the below to the `$providers` array in your `app/config/app.php`:

```php
'Chief\Bridge\Laravel\LaravelServiceProvider'
```

## Usage

We'll use the below command/handler for the usage examples:

```php
use Chief\Chief, Chief\Command;

class MyCommand implements Command {}
class MyCommandHandler {
    public function handle(MyCommand $command) { /* ... */ }
}
```   
   
#### Automatic handler resolution

When you pass a `Command` to `Chief::execute()`, Chief will automatically search for the relevant `CommandHandler` and call the `handle()` method:

```php
$chief = new Chief;
$chief->execute(new MyCommand);
```

By default, this will search for a `CommandHandler` with the same name as your `Command`, suffixed with 'Handler', in both the current namespace and in a nested `Handlers` namespace. 

So `Commands\FooCommand` will automatically resolve to `Commands\FooCommandHandler` or `Commands\Handlers\FooCommandHandler` if either class exists.

Want to implement your own method of automatically resolving handlers from commands? Implement your own version of the `Chief\CommandHandlerResolver` interface to modify the automatic resolution behaviour.
    
#### Handlers bound by class name

If your handlers don't follow a particular naming convention, you can explicitly bind a command to a handler by its class name:

```php
use Chief\Chief, Chief\NativeCommandHandlerResolver, Chief\Busses\SynchronousCommandBus;

$resolver = new NativeCommandHandlerResolver();
$bus = new SynchronousCommandBus($resolver);
$chief = new Chief($bus);

$resolver->bindHandler('MyCommand', 'MyCommandHandler');

$chief->execute(new MyCommand);
```
    
#### Handlers bound by object

Or, just pass your `CommandHandler` instance:
    
```php
$resolver->bindHandler('MyCommand', new MyCommandHandler);

$chief->execute(new MyCommand);
```

#### Handlers as anonymous functions

Sometimes you might want to quickly write a handler for your `Command` without having to write a new class. With Chief you can do this by passing an anonymous function as your handler:

```php	
$resolver->bindHandler('MyCommand', function (Command $command) {
    /* ... */
});

$chief->execute(new MyCommand);
```
    
#### Self-handling commands

Alternatively, you may want to simply allow a `Command` object to execute itself. To do this, just ensure your `Command` class also implements `CommandHandler`:

```php
class SelfHandlingCommand implements Command, CommandHandler {
    public function handle(Command $command) { /* ... */ }
}
$chief->execute(new SelfHandlingCommand);
```

## Decorators
Imagine you want to log every command execution. You could do this by adding a call to your logger in every `CommandHandler`, however a much more elegant solution is to use decorators.

Registering a decorator:

```php
$chief = new Chief(new SynchronousCommandBus, [new LoggingDecorator($logger)]);
```
    
Now, whenever `Chief::execute()` is called, the command will be passed to `LoggingDecorator::execute()`, which will perform some log action, and then pass the command to the relevant `CommandHandler`.

Chief provides you with some decorators out-the-box:

- *LoggingDecorator*: Log before and after all executions to a `Psr\Log\LoggerInterface`
- *EventDispatchingDecorator*: Dispatch an event to a `Chief\Decorators\EventDispatcher` after every command execution.
- *CommandQueueingDecorator*: Put the command into a Queue for later execution, if it implements `Chief\QueueableCommand`. (Read more under "Queued Commands")
- *TransactionalCommandLockingDecorator*: Lock the command bus when a command implementing `Chief\TransactionalCommand` is being executed. (Read more under "Transactional Commands")
    
Registering multiple decorators:

```php
// Attach decorators when you instantiate
$chief = new Chief(new SynchronousCommandBus, [
    new LoggingDecorator($logger),
    new EventDispatchingDecorator($eventDispatcher)
]);

// Or attach decorators later
$chief = new Chief();
$chief->pushDecorator(new LoggingDecorator($logger));
$chief->pushDecorator(new EventDispatchingDecorator($eventDispatcher));

// Or manually stack decorators
$chief = new Chief(
    new EventDispatchingtDecorator($eventDispatcher,
        new LoggingDecorator($logger, $context, 
            new CommandQueueingDecorator($queuer, 
                new TransactionalCommandLockingDecorator(
                    new CommandQueueingDecorator($queuer, 
                        new SynchronousCommandBus()
                    )
                )
            )
        )
    )
);
```
    
## Queued Commands

Commands are often used for 'actions' on your domain (eg. send an email, create a user, log an event, etc). For these type of commands where you don't need an immediate response you may wish to queue them to be executed later. This is where the `CommandQueueingDecorator` comes in to play.

Firstly, to use the `CommandQueueingDecorator`, you must first implement the `CommandQueuer` interface with your desired queue package:

```php
interface CommandQueuer {
    /**
     * Queue a Command for executing
     *
     * @param Command $command
     */
    public function queue(Command $command);
}
```

> An implementation of `CommandQueuer` for illuminate/queue is [included](https://github.com/adamnicholson/Chief/blob/master/src/Bridge/Laravel/IlluminateQueuer.php).

Next, attach the `CommandQueueingDecorator` decorator:

```php
$chief = new Chief();
$queuer = MyCommandBusQueuer();
$chief->pushDecorator(new CommandQueueingDecorator($queuer));
```
    
Then, implement `QueueableCommand` in any command which can be queued:

```php
MyQueueableCommand implements Chief\QueueableCommand {}
```

Then use Chief as normal:

```php
$command = new MyQueueableCommand();
$chief->execute($command);
```

If you pass Chief any command which implements `QueueableCommand` it will be added to the queue. Any commands which do *not* implement `QueueableCommand` will be executed immediately as normal.

If your commands implement `QueueableCommand` but you are not using the `CommandQueueingDecorator`, then they will be executed immediately as normal. For this reason, it is good practice to implement `QueueableCommand` for any commands which may be queued in the future, even if you aren't using the queueing decorator yet.

## Cached Command Execution

The `CachingDecorator` can be used to store the execution return value for a given command.

For example, you may have a `FetchUerReportCommand`, and an associated handler which takes a significant time to generate the "UserReport". Rather than re-generating the report every time, simply make `FetchUserReport` implement `CacheableCommand`, and the return value will be cached.

Data is cached to a `psr/cache` (PSR-6) compatible cache library.

> Chief does not supply a cache library. You must require this yourself and pass it in as a consturctor argument to the `CachingDecorator`.

Example:

```php
use Chief\CommandBus,
    Chief\CacheableCommand,
    Chief\Decorators\CachingDecorator;

$chief = new Chief();
$chief->pushDecorator(new CachingDecorator(
	$cache, // Your library of preference implementing PSR-6 CacheItemPoolInterface.
	3600 // Time in seconds that values should be cached for. 3600 = 1 hour.
));


    
class FetchUserReportCommand implements CacheableCommand { }

class FetchUserReportCommahdHandler {
	public function handle(FetchUserReportCommand $command) {
		return 'foobar';
	}
}

$report = $chief->execute(new FetchUserReportCommand); // (string) "foo" handle() is called
$report = $chief->execute(new FetchUserReportCommand); // (string) "foo" Value taken from cache
$report = $chief->execute(new FetchUserReportCommand); // (string) "foo" Value taken from cache


```

## Transactional Commands

Using the `TransactionalCommandLockingDecorator` can help to prevent more than 1 command being executed at any time. In practice, this means that you if you nest a command execution inside a command handler, the nested command will not be executed until the first command has completed.

Here's an example:

```php
use Chief\CommandBus;
use Chief\Command;
use Chief\Decorators\TransactionalCommandLockingDecorator;

class RegisterUserCommandHandler {
	public function __construct(CommandBus $bus, Users $users) {
		$this->bus = $bus;
	}
	
	public function handle(RegisterUserCommand $command) {
		$this->bus->execute(new RecordUserActivity('this-will-never-be-executed'));
		Users::create([
			'email' => $command->email,
			'name' => $command->name
		]);
		throw new Exception('Something unexpected; could not create user');
	}
}

$chief = new Chief();
$chief->pushDecorator(new TransactionalCommandLockingDecorator());

$command = new RegisterUserCommand;
$command->email = 'foo@example.com';
$command->password = 'password123';

$chief->execute($command);
```

So what's happening here? When `$chief->execute(new RecordUserActivity('registered-user'))` is called, that command is actually dropped into an in-memory queue, which will not execute until `RegisterCommandHandler::handle()` has finished. In this example, because we're showing that an `Exception` is thrown before the method completes, the `RecordUserActivity` command is never actually executed.


## Dependency Injection Container Integration
Chief uses a `CommandHandlerResolver` class which is responsible for finding and instantiating the relevant `CommandHandler` for a given `Command`. 

If you want to use your own Dependency Injection Container to control the actual instantiation, just create your own class which implements `Chief\Container` and pass it to the `CommandHandlerResolver` which is consumed by `SynchronousCommandBus`.

For example, if you're using Laravel:

```php
use Chief\Resolvers\NativeCommandHandlerResolver,
    Chief\Chief,
    Chief\Busses\SynchronousCommandBus,
    Chief\Container;

class IlluminateContainer implements Container {
    public function make($class) {
        return \App::make($class);
    }
}

$resolver = new NativeCommandHandlerResolver(new IlluminateContainer);
$chief = new Chief(new SynchronousCommandBus($resolver));
$chief->execute(new MyCommand);
```

Containers have already been provided for :

`Illuminate\Container`:

```php
$container = new \Illuminate\Container\Container;
$resolver = new NativeCommandHandlerResolver(new \Chief\Bridge\Laravel\IlluminateContainer($container));
$chief = new Chief(new \Chief\Busses\SynchronousCommandBus($resolver));
```

`League\Container`:

```php
$container = new \League\Container\Container;
$resolver = new NativeCommandHandlerResolver(new \Chief\Bridge\League\LeagueContainer($container));
$chief = new Chief(new \Chief\Busses\SynchronousCommandBus($resolver));
```

`Psr\Container` compatible container:

```php
$resolver = new NativeCommandHandlerResolver(new \Chief\Bridge\Psr\PsrContainer($psrContainer));
$chief = new Chief(new \Chief\Busses\SynchronousCommandBus($resolver));
```

## Contributing

We welcome any contributions to Chief. They can be made via GitHub issues or pull requests.

## License

Chief is licensed under the MIT License - see the `LICENSE.txt` file for details

## Author

Adam Nicholson - adamnicholson10@gmail.com
