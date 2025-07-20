---
layout: post
title:  Replace dependency injection and mocking with algebraic effects
date:   2025-06-28
categories: programming php fibers dependency injection mocking effects
---

The main idea is, instead of injecting what you need, you ask for it using an **effect**.

If you don't know what an **algeabraic effect** is, you can read about it on [StackOverflow](https://stackoverflow.com/a/57280373) or [Wikipedia](https://en.wikipedia.org/wiki/Effect_system).

To quote the Stackoverflow answer:

> In short, algebraic effects are an exception mechanism which lets the throwing function continue its operation.

Since PHP does not support effects, I'm using [fibers](https://www.php.net/manual/en/language.fibers.php) to simulate it.

I'm using object with the suffix `Effect` to denote effects.

Before we knew about injection, we usually asked about the database connection in local scope, as such:

```php
class DoAThingCommand
{
    public function run(array $data): void
    {
        if ($data['foo'] == 'bar') {
            $sql = ... // omitted
            $db = OpenDatabase();
            $result = $db->select($sql);
        }
    }
}
```

If you instead inject the database connection, so that it can be mocked during unit-test, it looks like this:

```php
class DoAThingCommand
{
    private $db;

    public function __constructor(Db $db)
    {
        $this->db = $db;
    }
    
    public function run(array $data): void
    {
        if ($data['foo'] == 'bar') {
            $sql = ... // omitted
            $result = $this->db->select($sql);
        }
    }
}
```

But you can avoid the hazzle of injection entirely if you instead use an effect system to "ask" about the connection:

```php
class DoAThingCommand
{
    public function __invoke(array $data): void
    {
        if ($data['foo'] == 'bar') {
            $sql = ... // omitted
            $db = Fiber::suspend(new OpenDatabaseEffect());
            $result = $db->select($sql);
        }
    }
}
```

In fact, you might not need a connection at all - just send the query to the effect handler instead:

```php
class DoAThingCommand
{
    public function __invoke(array $data): void
    {
        if ($data['foo'] == 'bar') {
            $sql = ... // omitted
            $result = Fiber::suspend(new SqlQueryEffect($sql));
        }
    }
}
```

This method might be suboptimal when you're dealing with multiple database connections at once.

The code needs a so called **effect handler** at the top scope, which is the fiber code.

```php
$fiber = new Fiber(new DoAThingCommand());
$data = [
    'foo' => 'bar'
];
$effect = $fiber->start($data);
$db = OpenDatabase();
while (!$fiber->isTerminated()) {
    $data = null;
    if ($effect instanceof Effect) {
        if ($effect instanceof SqlQueryEffect) {
            $data = $db->select($effect->sql);
        } else {
            throw new RuntimeException('Unsupported effect class');
        }
    } else {
        // Other Fiber usage?
    }
    if ($data) {
        $effect = $fiber->resume($data);
    }
}
$ret = $fiber->getReturn();
```

The same method can be used for:

* StdoutEffect and StderrEffect to output data to streams
* Setting headers
* RandEffect to get a random number
* CacheEffect to do caching
* TraceEffect or similar for logging

## Testing

To compare how unit-test might look like for the different cases. Here assuming the command class iterates and sum the database result.

Using PHPUnit with mocking:

```php
public function testCommandMock(): void
{
    $db = $this
        ->getMockBuilder(Db::class)
        ->getMock();
    $db->method('select')->willReturn([1, 2, 3]);
    $command = new DoAThingCommand($db);
    $data = ['foo' => 'bar'];
    $ret = $command($data);
    $this->assertEquals($ret, 6);
}
```

Same test using effects:

```php
public function testCommandEffects(): void
{
    $fiber = new Fiber(new DoAThingCommand());
    $data = ['foo' => 'bar'];
    $value = $fiber->start($data);
    while (!$fiber->isTerminated()) {
        if ($value instanceof SqlQueryEffect) {
            $queryResult = [1, 2, 3];
            $value = $fiber->resume($queryResult);
        } else {
            $value = $fiber->resume();
        }
    }
    $ret = $fiber->getReturn();
    $this->assertEquals($ret, 6);
}
```

It can probably be shortened with a mock effect handler class.

NB: Both mocking and effect handler are white-box testing, assuming internal knowledge of a function.

The question is how this technique scales with the introduction of more and more injected classes. Imagine a command object which needs access to a SQL database, Redis, file system, uses curl and logging. Mocking would involve at least five mocks:

```php
$db = $this
    ->getMockBuilder(Db::class)
    ->getMock();
$redis = $this
    ->getMockBuilder(Redis::class)
    ->getMock();
$file = $this
    ->getMockBuilder(File::class)
    ->getMock();
$curl = $this
    ->getMockBuilder(Curl::class)
    ->getMock();
$logger = $this
    ->getMockBuilder(Logger::class)
    ->getMock();
```

Plus logic for mocking the methods.

Depending on how much each class is used, the effect tests would be something like:

```php
$effect = $fiber->start($data);
while (!$fiber->isTerminated()) {
    if ($effect instanceof SqlQueryEffect) {
        $queryResult = [1, 2, 3];
        $effect = $fiber->resume($queryResult);
    } else if ($effect instanceof RedisEffect) {
        // Logic omitted
    } else if ($effect instanceof FileAccessEffect) {
        // Logic omitted
    } else if ($effect instanceof LogEffect) {
        // Logic omitted
    } else if ($effect instanceof CurlEffect) {
        // Logic omitted
    } else {
        $effect = $fiber->resume();
    }
}
```

Here we might start to see a gain in reduced complexity in test logic, especially if we apply some helper functions to just give the test an array of expected effects instead of this if-statement spagetti.

```php
$effectHelper = new MockEffectHelper([
    SqlQueryEffect::class => [1, 2, 3],
    RedisEffect::class => true,  // success
    FileAccessEffect::class => $dummyFileContent,
    LogEffect => true,
    CurlEffect => $dummyCurlReturnValue,
]);
```

You might argue that a command object with so much effectful logic shouldn't be unit tested at all, but rather integrity tested. How is that better, though? We might refrain from writing unit tests sometimes, because we lack proper tools to deal with effectfulness.

## TODO

* Can't combine with other fibers, e.g. Amphp?
* Counter argument: If you need to mock the order of function calls, you're coding wrong in the first place

## Links

For more info on effect handlers, you can read [this section](https://ocaml.org/manual/5.3/effects.html) of the OCaml manual.

Some advanced use-cases for effect handlers can be found [here](https://github.com/ocaml-multicore/effects-examples?tab=readme-ov-file).

[Koka](https://koka-lang.github.io/koka/doc/book.html#why-effects) is a language that supports typed effects.
