---
layout: post
title:  A type-safe query builder for PHP using Psalm
date:   2022-06-11
categories: programming
---

Caveats below.

Main point: Use Psalm type annotations to statically disallow faulty states.

[Psalm](https://psalm.dev) is a static analyser for PHP.

We will use two quite special annotations:

* `@psalm-this-out`
* `@psalm-if-this-is`

The first one will tell Psalm to change the type of `this` after a function call. The second one will check the type of `this` at a function call, and fail if it's not the annotated type.

Below is a simple query builder, with the body of functions omitted.

```php
/**
 * @template S
 * @template T
 */
class QueryBuilder
{
    /**
     * @psalm-this-out QueryBuilder<HasSelect, T>
     */
    public function select(): void
    {
    }

    /**
     * @psalm-this-out QueryBuilder<S, HasFrom>
     */
    public function from(): void
    {
    }

    public function where(): void
    {
    }

    /**
     * @psalm-if-this-is QueryBuilder<HasSelect, HasFrom>
     */
    public function execute(): void
    {
    }
}
```

How to use it:

```php
$qb = new QueryBuilder();
$qb->select();
$qb->from();
$qb->execute();  // $qb has type QueryBuilder<HasSelect, HasFrom>, so this call is valid
```

Example of failing use:

```php
$qb = new QueryBuilder();
$qb->select();
$qb->execute();  // $qb has type QueryBuilder<HasSelect, mixed> - not valid
```

This will fail with:

```
ERROR: IfThisIsMismatch - Class type must be QueryBuilder<HasSelect, HasFrom>
current type QueryBuilder<HasSelect, mixed>
```

In short, you can statically make sure all your SQL queries are correct, _without_ running the code.

Some caveats:

* These annotations do not work with method chaining
* Aliasing will confuse the type-checker, e.g. using `$foo = $qb`, Psalm will not understand they are pointing to the same object; one possible solution is to forbid aliasing, that is, only allow _one_ variable pointing to an object at a time (related to ownership)
* It would probably be really hard to build a _complete_ type-safe SQL query builder

This is a little bit like type-state programming, which _could_ allow for type-safe embedded DSL if the problems could be adressed.
