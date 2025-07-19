---
layout: post
title:  Replace dependency injection and mocking with algebraic effects
date:   2025-06-28
categories: programming php fibers dependency injection mocking effects
---

DRAFT

The main idea is, instead of injecting what you need, you ask for it using an effect.

If you don't know what an algeabraic effect is, you can read about it on [StackOverflow](https://stackoverflow.com/a/57280373) or [WikiPedia](https://en.wikipedia.org/wiki/Effect_system).

Since PHP does not support effects, I'm using [fibers](https://www.php.net/manual/en/language.fibers.php) to simulate it.

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

Classic code injecting a database handle.

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

This is how the code would look like using an effect instead:

```php
class DoAThingCommand
{
    public function __invoke(array $data): void
    {
        if ($data['foo'] == 'bar') {
            $sql = ... // omitted
            $result = Fiber::suspend(new QueryEffect($sql));
            echo 'Database query returned the value: ' . $result, PHP_EOL;
        }
    }
}

```

The code need a top "effect handler", which is the fiber code.

```php
$fiber = new Fiber(new DoAThingCommand());
$data = [
    'foo' => 'bar'
];
$value = $fiber->start($data);
while (!$fiber->isTerminated()) {
    $data = null;
    if ($value instanceof Effect) {
        if ($value instanceof QueryEffect) {
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
* CacheEffect to cache

And of course you can do exceptions in this manner, too.

TODO

* Can't combine with other fibers, e.g. Amphp?
* Example of test code
* Counter argument: If you need to mock the order of function calls, you're coding wrong in the first place

## Links

Some advanced use-cases for effect handlers can be found here: https://github.com/ocaml-multicore/effects-examples?tab=readme-ov-file
