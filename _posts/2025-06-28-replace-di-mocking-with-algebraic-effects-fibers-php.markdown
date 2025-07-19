---
layout: post
title:  Replace dependency injection and mocking with algebraic effects
date:   2025-06-28
categories: programming php fibers dependency injection mocking effects
---

DRAFT

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

The code needs a so called **effect handler**, which is the fiber code.

```php
$fiber = new Fiber(new DoAThingCommand());
$data = [
    'foo' => 'bar'
];
$value = $fiber->start($data);
while (!$fiber->isTerminated()) {
    $data = null;
    if ($value instanceof Effect) {
        if ($value instanceof SqlQueryEffect) {
            $data = 'Db value';
        } else {
            throw new RuntimeException('Unsupported effect class');
        }
    } else {
        // Other Fiber usage?
    }
    if ($data) {
        $value = $fiber->resume($data);
    }
}
```

The same method can be used for:

* StdoutEffect and StderrEffect to output data to streams
* Setting headers
* RandEffect to get a random number
* CacheEffect to do caching
* TraceEffect or similar for logging

TODO

* Can't combine with other fibers, e.g. Amphp?
* Example of test code
* Counter argument: If you need to mock the order of function calls, you're coding wrong in the first place

## Links

For more info on effect handlers, you can read [this section](https://ocaml.org/manual/5.3/effects.html) of the OCaml manual.

Some advanced use-cases for effect handlers can be found [here](https://github.com/ocaml-multicore/effects-examples?tab=readme-ov-file).

[Koka](https://koka-lang.github.io/koka/doc/book.html#why-effects) is a language that supports typed effects.
