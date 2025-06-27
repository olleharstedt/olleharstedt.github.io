---
layout: post
title:  Replace DI and mocking with algebraic effects
date:   2025-06-28
categories: programming php fibers di mocking effects
---

DRAFT

Instead of injecting what you need, ask for it using an effect.

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

Same code using an effect class and fibers.

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
* RandEffect to get a random number
* CacheEffect to cache

And of course you can do exceptions in this manner, too.

TODO

* Can't combine with other fibers, e.g. Amphp?
* Example of test code
* Counter argument: If you need to mock the order of function calls, you're coding wrong in the first place

## Links

Some advanced use-cases for effect handlers can be found here: https://github.com/ocaml-multicore/effects-examples?tab=readme-ov-file
