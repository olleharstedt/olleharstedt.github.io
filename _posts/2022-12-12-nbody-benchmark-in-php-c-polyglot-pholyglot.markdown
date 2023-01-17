---
layout: post
title:  The nbody benchmark in PHP+C polyglot code (as compiled by Pholyglot 0.0.-1-alphacow)
date:   2022-12-12
categories: programming
---

TODO: Malloc

_Pholyglot_ is a PHP-to-PHP+C transpiler. The output is C code that's also runnable by PHP, so called polyglot code.

_Pholly_ is the PHP dialect that's supported by Pholyglot (mostly a subset + some required annotations).

This blog post describes the features needed inside the Pholyglot compiler to complete the nbody benchmark from [benchmarkgames](https://benchmarksgame-team.pages.debian.net/benchmarksgame/performance/nbody.html).

Topics:

* Polymorphic arrays
* Class/struct base
* Loops (foreach and do-while)
* Some kind of generics for `array_slice`

**Arrays**

The array is a very basic struct like so:

```c
struct array {
    uintptr_t* thing;
    size_t length;
};
```

All it contains is a pointer to the actual array data and its length.

The struct makes it very easy to create a `count` macro that mirrors the PHP function:

```
#define count(x) x.length
```

It is possible to make a C macro to init an array similar as to PHP, but allocation would then only be on stack:

```
#define array(...) {__VA_ARGS__}
```

Maybe in some cases this can be used, but in general I'll use a helper macro/function instead:

```
#define array_make(type, i, ...) {.thing = (type[]) array(__VA_ARGS__), .length = i}
```

The corresponding PHP code just discards first two arguments:

```php
function array_make($type, $length, ...$values) { return $values; }
```

TODO: Still stack alloc here

This makes array init a bit more akward but still C+PHP compatible:

```php
#if __PHP__
define("int", "int");
#endif
#__C__ array
$arr = array_make(int, 3, 1, 2 3);
```

Arrays in PHP have value semantics. To avoid implementing this in C, I enforce all arrays to be passed by reference in Pholly.

I'm limiting myself to fixed-size arrays here. Linked list and hash tables will be a fun exercise for the future (hellooo SplDoublyLinkedList).

To get proper type-casting in C, and because I'm using a struct for arrays, I'm using a helper macro for array access instead of the built-in syntax:

```
#define array_get(type, arr, i) ((type*) arr.thing)[i]
```

Obviously this means a performance overhead when run as PHP, with a function call at each array access.

**Class/struct**

If you don't do inheritance, a class is basically a bucket of data with some function pointers using "this" or "self" as first implicit argument. That's what I go with here.

First of all, just macro the "class" keyword:

```
#define class struct
```

We'll make all properties public and redefine the "public" keyword at each property to its proper type:

```
class Point {
#define public float
#define __object_property_x $__object_property_x
public $__object_property_x;
#undef public
};
```

The clunky `__object_property_x` is needed since PHP implies the dollar sign at property access and C of course does not.

Methods are not really needed for the nbody benchmark, but for completeness:

Function pointer struct members only in C.

Function body same in both C and PHP.

Struct ends before PHP class.

TODO

Fun fact, the `new` keyword in PHP can also be called with parenthesis and a string, which we'll abuse for a `new` C macro:

```
#define new(x) x ## __constructor(malloc(sizeof(struct x)))
```

This assumes that a constructor function will exist, e.g. `Point__constructor` used to init function pointers.

Thanks to these solutions, we finally get code like:

```php
#if __PHP__
define("Point", "Point");
#endif
#__C__ array
$points = array_make(Point, 2, new(Point), new(Point));
```

which is pretty readable, I'd say.

Another pain-point is the reference notation. PHP has value semantics for arrays. I didn't want to implement that in C, so instead I chose to enforce references for array as arguments to functions. The PHP notation `&` exists in C++ but not C. Because of a regression, it's not possible anymore in PHP 8.1 and up to but comments between reference and variable, else I could have done:

```php
function foo(
#if __PHP__
&
#endif
$points
);
```

The only other solution I could think of is to duplicate the function signature when there's an array passed around:

```php
#__C__ void foo(array $points)
#if __PHP__
function foo(array &$points)
#endif
{
}
```

So close tho. :(

**Looping**

The PHP `foreach` loop can simply transpile down to a classic for-loop that runs in both PHP and C. Same goes for do-while.

```php
foreach ($bodies as $body) { ... }
```

will become:

```php
#__C__ int
$i = 0;
for (; $i < count($bodies); $i = $i + 1) {
    #__C__ Body
    $body = array_get(Body, $bodies, $i);
}
```

Stay tuned for the next version: pholyglot-0.0.-2-betachicken.

## Scrap

What's yet missing is different allocation strategies, e.g. using ref counting, Boehm or stack allocation as above with `alloca`. I'm thinking of using the `_Generic` C functionality for this, with a hard-coded list inside the macro created during the transpilation.

