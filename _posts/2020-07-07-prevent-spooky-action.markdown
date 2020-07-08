---
layout: post
title:  Prevent spooky-action-at-a-distance by runtime ownership check using refcount
date:   2020-07-08
categories: php
---

As an alternative design pattern to immutable objects, you can also check an object's refcount to make sure it's less than 2.

```php
class Foo {
    public function __set($name, $val) {
        // if refcount > 1, throw exception
    }
}

$foo = new Foo();
$foo->prop = 'something';
// ... more initialisation
dosomething($foo);  // Function cannot set a prop
```

This pattern is usable for dependency injection where the setup is more complicated than in an immutable object, but access should be limited to dependent classes (e.g., not closing a database connection).

Here's one way to check refcount (from <a href="https://stackoverflow.com/a/3764809/2138090">stackoverflow</a>):

```php
function refcount($var)
{
    ob_start();
    debug_zval_dump($var);
    $dump = ob_get_clean();

    $matches = array();
    preg_match('/refcount\(([0-9]+)/', $dump, $matches);

    $count = $matches[1];

    //3 references are added, including when calling debug_zval_dump()
    return $count - 3;
}
```
