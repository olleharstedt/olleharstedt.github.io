---
layout: post
title:  How to use enums as result type in PHP 8.1
date:   2021-03-31
categories: php
---

Different types of error handling

* Exception
* Mixed return type
* Return argument (depreceated)
* Return tuple

No broad concensus, new language makes different decisions:

* Go, Rust - no exceptions
* Koka - an exception is just a special case of effect handlers
* Haskell, another monad (and also other things I don't know about)

Another alternative to above mentiond techniques is the `Result` type from OCaml:

```ocaml
type ('a, 'e) result = 
    | Ok of 'a
    | Error of 'e
```

This defines a type `result` that is _either_ `Ok` or `Error`. Further more, it carries data of type `'a` or `'e`, meaning, any type.

This is called algebraic data-types. The PHP enum functionality is based on this.

```php
enum Result: mixed {
    case Ok = null;
    case Error = null;
}
```

TODO: Result can only carry int and string. Why?

We need generics to make it type-safe (not "mixed"). Don't know if Psalm or PHPStan supports this yet.

Example:

```php
function getPost(int? $courseId): Result
{
    if (is_null($courseId) {
        return Result::Error("Course id cannot be null");
    }
}
```
