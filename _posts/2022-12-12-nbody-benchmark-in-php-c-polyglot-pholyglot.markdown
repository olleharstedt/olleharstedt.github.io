---
layout: post
title:  The nbody benchmark in PHP+C polyglot code (as compiled by Pholyglot 0.0.-1-alphacow)
date:   2022-12-12
categories: programming
---

TODO

The target is semi-readable PHP+C polyglot code.

```c
#define array(...) {__VA_ARGS__}
#define array_make(type, i, ...) {.thing = (type[]) array(__VA_ARGS__), .length = i}
typedef struct array array;
struct array {void* thing; size_t length; };
```

Make array struct that keeps the length of the array. The type is known during compile-time.

```c
#define array_get(type, arr, i) ((type*) arr.thing)[i]
```

A pain-point in not being able to use "normal" array access notation, which is the same in both C and PHP. I chose this so I could keep length of array in the struct. Obviously it means a performance overhead when run as PHP, with a function call at each array access.

```c
#define new(x) x ## __constructor(alloca(sizeof(struct x)))
```

PHP `new` keyword also can be used as `new(<string of classname)`, which works with the above C macro. Assumes a constructor function will exist, e.g. `Point__constructor`.

What's yet missing is different allocation strategies, e.g. using ref counting, Boehm or stack allocation as above with `alloca`. I'm thinking of using the `_Generic` C functionality for this, with a hard-coded list inside the macro created during the transpilation.

Thanks to these solutions, we finally get code like:

```php
//?>
array
//<?php
$points = array_make(Point, 2, new(Point), new(Point));
```

which is pretty readable, I'd say.

Another pain-point is the reference notation. PHP has value semantics for arrays. I didn't want to implement that in C, so instead I chose to enforce references for array as arguments to functions. The PHP notation `&` exists in C++ but not C. Because of a regression, it's not possible anymore in PHP 8.1 and up to but comments between reference and variable, else I could have done:

```
function foo(
#if __PHP__
&
#endif
$points
);
```

The only other solution I could think of is to duplicate the function signature when there's an array passed around:

```
//?>
void foo(array $points)
//<?php
#if __PHP__
function foo(array &$points)
#endif
{
}
```

So close tho. :(

Stay tuned for the next version: pholyglot-0.0.-2-betachicken.
