---
layout: post
title:  The nbody benchmark in PHP+C polyglot code (as compiled by Pholyglot 0.0.-1-alphacow)
date:   2022-12-12
categories: programming
---

_[Pholyglot](https://github.com/olleharstedt/pholyglot)_ is a PHP-to-PHP+C transpiler. The output is C code that's also runnable by PHP, so called polyglot code.

_Pholly_ is the PHP dialect that's supported by Pholyglot (mostly a subset + some required annotations).

This blog post describes the features needed inside the Pholyglot compiler to complete the nbody benchmark from [benchmarksgame](https://benchmarksgame-team.pages.debian.net/benchmarksgame/performance/nbody.html).

Topics:

* Polymorphic arrays
* Class/struct base with "methods"
* Loops
* Some kind of generics for `array_slice` function

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

(Note: This is still a stack allocation, it will have to be rewritten with a malloc etc.)

The corresponding PHP code just discards the first two arguments:

```php
function array_make($type, $length, ...$values) { return $values; }
```

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
class Body {
#define public float
#define __prop_vx $__prop_vx
public $__prop_vx;
#undef public
};
```

The clunky `__prop_vx` is needed since PHP implies the dollar sign at property access and C of course does not.

Methods are not really needed for the nbody benchmark, but for completeness:

```
#__C__ void (*offsetMomentum) (Body $__self, float $px, float $py, float $pz); 
```

Function pointer struct members only in C.

Pass around `$__self` explicitly since C has no `this` concept.

The important part is that the _method body_ is the same in both C and PHP. The function signature is duplicated, though.

```php
#__C__ void Body__offsetMomentum (Body $__self, float $px, float $py, float $pz)
#if __PHP__
public function offsetMomentum(Body $__self, float $px, float $py, float $pz): void
#endif
{
    #__C__ float
    $pi = 3.1415926535897931;
    #__C__ float
    $solarmass = 4. * $pi * $pi;
    $__self->__prop_vx = (0. - $px) / $solarmass;
    $__self->__prop_vy = (0. - $py) / $solarmass;
    $__self->__prop_vz = (0. - $pz) / $solarmass;
}
```

Method calling is then polyglot, like so:

```php
$b->offsetMomentum($b, $px, $py, $pz);
```

Fun fact, the `new` keyword in PHP can also be called with parenthesis and a string, which we'll abuse for a `new` C macro:

```
#define new(x) x ## __constructor(malloc(sizeof(struct x)))
```

This assumes that a constructor function will exist, e.g. `Body__constructor` used to init function pointers.

Thanks to these solutions, we finally get code like:

```php
#if __PHP__
define("Body", "Body");
#endif
#__C__ array
$bodies = array_make(Body, 2, new(Body), new(Body));
```

which is pretty readable, I'd say.

**Looping**

The PHP `foreach` loop can simply transpile down to a classic for-loop that runs in both PHP and C. Same goes for do-while.

```php
foreach ($bodies as $body) { ... }
```

will transpile to:

```php
#__C__ int
$i = 0;
for (; $i < count($bodies); $i = $i + 1) {
    #__C__ Body
    $body = array_get(Body, $bodies, $i);
}
```

**Generics**

I didn't _really_ add support for generics, just the needed internal parts to tell the compiler that `array_slice` expects the same type out as it gets in. Future development would adapt the `@template T` notation from [Psalm](https://psalm.dev/docs/annotating_code/templated_annotations/) and other tools.

**Performance**

Well, obviously compiled C will be faster than PHP in numerical calculations, that's trivially true. Even more so when the polyglot PHP code has a couple of slowdowns, like the `array_get` access function. More interesting benchmarks would be with proper database and file IO, etc.

**Code**

[Full code listing of the Pholly code](https://gist.github.com/olleharstedt/457030e66b311f1642f504d601391280)

[Full code listing of the transpiled PHP+C code](https://gist.github.com/olleharstedt/07f0172423d167d97d813c954507ac22)

**Future milestones**

I'd like to do one of the following next:

* A-star algorithm, testing dynamic memory allocation strategies
    * Especially interested in if per-variable memory allocation is feasible, like `$body = /** @alloc stack */ new Body();`, allowing programmers to opt-out of the default GC when needed. [Odin](https://odin-lang.org/docs/overview/#allocators) has something similar.
* A simple REST API call, using MySQL, reading a config file, perhaps curl

Stay tuned for the next version: pholyglot-0.0.-2-betachicken.
