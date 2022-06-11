---
layout: post
title:  A type-safe query builder for PHP using Psalm
date:   2022-06-11
categories: programming
---

**Main point: Use Psalm's type-annotations to statically disallow faulty states in a class.**

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
    public function execute(): mixed
    {
        return 'result';
    }
}
```

Example usages:

```php
// $qb has type QueryBuilder<mixed, mixed>
$qb = new QueryBuilder();
// $qb has type QueryBuilder<HasSelect, mixed>
$qb->select();
// $qb has type QueryBuilder<HasSelect, HasFrom>
$qb->from();
// $qb has type QueryBuilder<HasSelect, HasFrom>, so this call is valid
$qb->execute();
```

It's also possible to change the order of the function calls:

```php
// $qb has type QueryBuilder<mixed, mixed>
$qb = new QueryBuilder();
// $qb has type QueryBuilder<null, HasFrom>
$qb->from();
// $qb has type QueryBuilder<HasSelect, HasFrom>
$qb->select();
// $qb has type QueryBuilder<HasSelect, HasFrom>, so this call is valid
$qb->execute();
```

Example of failing use:

```php
$qb = new QueryBuilder();
$qb->select();
// $qb has type QueryBuilder<HasSelect, mixed> - not valid
$qb->execute();
```

This will fail with:

```
ERROR: IfThisIsMismatch - Class type must be QueryBuilder<HasSelect, HasFrom>
current type QueryBuilder<HasSelect, mixed>
```

In short, you can statically make sure your SQL queries are correct, _without_ running the code.

**Caveats:**

* These annotations do not work with method chaining in current version of Psalm
* Aliasing will confuse the type-checker, e.g. setting `$foo = $qb`, Psalm will not understand they are pointing to the same object; one possible solution is to forbid aliasing, that is, only allow _one_ variable pointing to an object at a time (related to ownership and uniqueness)
* It would probably be really hard to build a type-safe query builder for _all_ possible SQL queries

**Related concepts:**

* This is a little bit like type-state programming, which _could_ allow for type-safe embedded domain-specific language (EDSL) if the problems could be adressed.
* Also see tagless-final, another way to do type-safe EDSL in functional programming
