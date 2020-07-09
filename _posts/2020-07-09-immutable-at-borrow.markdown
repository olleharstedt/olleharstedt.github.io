---
layout: post
title:  Dependency injection and the principle of least privilege
date:   2020-07-08
categories: php
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/access.jpg" alt="Access denied" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
_Access denied_
{: refdef}

## Introduction

Dependency injection - being explicit with dependencies - gives you a modular design but it does not automatically respect the [Principle of least privilege](https://en.wikipedia.org/wiki/Principle_of_least_privilege). This can lead to spooky-action-at-a-distance and in general _fragile composition_; if you inject a database connection, the dependent classes should usually not have access to close that connection; what you're doing is injecting _global state_. 

Ways to fix this:

* Wrap the dependencies in access-limiting decorators (could lead to lots of boilerplate code)
* _Only_ inject immutable classes (hard to do in practice, since most frameworks don't respect this)
* Never share dependencies

Another mitigating design pattern is outlined in this article, where PHP's refcount is used to track ownership and where ownership decides access level.

## Ownership

Most people know about ownership from C++ and Rust, but there is also research about ownership semantics in OOP languages like Java. In this article, there are two ownership (and access) levels:

* Owner
* Borrower

<div style='margin: 1em 3em;'>
<table>
<tr>
<td><span class='fa fa-icon fa-info-circle fa-2x'></span></td>
<td>
Other ownership systems also have "peer" as an access level; see further reading below.
</td>
</tr>
</table>
</div>

It's not possible to explicitly move ownership in this implementation.

## Working example

The following code will fail with a `NoOwnershipException` if `updateAllPosts()` closes the connection:

```php
$connection = new OwnershipConnection();
$connection->open();
$ps = new PostService($connection);
$ps->updateAllPosts();
$connection->close();
```

## Implementation

Custom exception at access abuse:

```php
class NoOwnershipException extends Exception {}
```

Trait to include in all access-controlling classes:

```php
trait OwnershipTrait
{
    /**
     * @return void
     * @throws NoOwnershipException
     * @see https://stackoverflow.com/a/3764809/2138090
     */
    private function failForBorrower()
    {
        ob_start();
        debug_zval_dump($this);
        $dump = ob_get_clean();

        $matches = array();
        preg_match('/refcount\(([0-9]+)/', $dump, $matches);

        // NB: -2 because debug_zval_dump creates a ref at use
        $count = $matches[1] - 2;

        if ($count > 1) {
            throw new NoOwnershipException('No access');
        }
    }
}
```

Here's a simple example using Yii 2 database connection, where access is abused by closing the connection after use.

```php
use yii\db\Connection;

class PostService
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return void
     */
    public function updateAllPosts()
    {
        $command = $this->connection->createCommand('UPDATE post SET status=1');
        $command->execute();
        $this->connection->close();  // Ooops!
    }
}
```

The problem with the bug above is that the program does not fail at location. Instead, the program will fail at the next query that uses the same database connection, wherever that is, and the developer will have to hunt it down manually. With a `NoOwnershipException`, you get the error at the right place and time.

How the connection class could be defined:

```php
class OwnershipConnection extends Connection
{
    use OwnershipTrait;

    public function open()
    {
        $this->failForBorrower();
        parent::open();
    }

    public function close()
    {
        $this->failForBorrower();
        parent::close();
    }
}
```

Obviously this is the same amount of boilerplate as a decorator - it _is_ a decorator. But it could also be implemented directly by a framework.

This pattern also works for factories, since it preserves refcount = 1 for the owner at function return.

## Pros and cons

Pros:

* Automatic "sharing without oversharing"
* More flexibly than decorators
* Fails directly at access abuse

Cons:

* Depends on implementation of PHP (refcount); if they change to tracing GC, the code would fail, possibly silently
* Can't get refcount in a sensible way without installing an extra extension
* No explicit semantics - you have to "know" if you're getting a borrowed and not owned object

## Further reading

If you're intersted in more use-cases of ownership semantics than just malloc/free discipline, here are some keywords to search for:

* Universe Types and ownership inference
* Owner-as-modifier vs owner-as-dominator
* Rust and ownership
* E and capabilities
* Clean and uniqueness
* Linear types, for example in Haskell or ML
