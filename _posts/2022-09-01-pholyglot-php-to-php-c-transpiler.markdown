---
layout: post
title:  Pholyglot version 0.0.0 (PHP to PHP+C polyglot transpiler)
date:   2022-06-11
categories: programming
---

[Pholyglot](https://github.com/olleharstedt/pholyglot) is a small and never-to-be-completed hobby project transpiler that takes a subset of PHP as input, and outputs [polyglot](https://en.wikipedia.org/wiki/Polyglot_(computing)) code that can be run in both PHP and C. See example below.

The PHP subset is called "Pholly", of course. :)

**Example Pholly code:**

```php
<?php // @pholyglot

class Point {
    public int $x;
    public int $y;
}

function main(): int {
    $p = new Point();
    $p->x = 100;
    printf("%d\n", $p->x);
    return 0;
}
```

**Transpiled to:**

```php
//<?php echo "\x08\x08"; ob_start(); ?>
#include <stdio.h>
#include <glib.h>
#define function
#define class struct
#define __PHP__ 0
#define new_(x) alloca(sizeof(struct x))
#if __PHP__//<?php
class GString { public $str; public function __construct($str) { $this->str = $str; } }
function g_string_new(string $str) { return new GString($str); }
function g_string_append(GString $s1, string $s2) { return new GString($s1->str . $s2); }
function new_($class) { return new $class; }
#endif//?>
//<?php
 
class Point {
    #define public int
#define __object_property_x $__object_property_x
    public $__object_property_x;
#undef public
#define public int
#define __object_property_y $__object_property_y
    public $__object_property_y;
#undef public
 
};
#if __PHP__
define("Point", "Point");  // Needed to make new_() work with C macro
#endif
#__C__ int
function main()
{
    #__C__ struct Point*
    $p
    = new_(Point);
    $p->__object_property_x
    = 100;
     printf("%d", $p->__object_property_x);
    return 0;
}
// ?>
// <?php ob_end_clean(); main();
```

To break it down:

* To not output noisy C-code when running in PHP, we need to use an output buffer. The "\x08" char is a backspace, to remove the leading `//`.

    ```//<?php echo "\x08\x08"; ob_start(); ?>```
* We can use `#define` without problem, since it's just a comment in PHP.
* `function` is defined as empty space.
* `class` is defined as `struct`, which works in this simple case with only properties, no methods or inheritance.
* We add constant `__PHP__` to skip parts of the code that should only be run in PHP.
* `new_` is a macro in C, and a function in PHP that just returns `new $class`.
* Since we're using glib for string processing, we add stubs for PHP (actually not used in this example).
* For each class property, we redfine the meaning of `public` to its type in C.
* To enable the difference of syntax of property access syntax in C vs PHP, `$point->x` or `$point->$x`, we namespace each property with `__object_property_` and define it.
* Since `sizeof` does not expect a string, we create a PHP constant with same name as the class, to use instead of "Point".
* `#__C__` is actually not a macro, but a word that will be destroyed by sed in the C Makefile. C macros cannot contain the hash character. Using a PHP constant `INT` that in C expands to `int` is not possible, since it breaks PHP syntax to have a constant before a function. Using `INT;` instead is not possible, since C macros cannot contain semicolon. Neither can the macro tool m4.
* `$p` is assigned to new point, and the property `x` is set.
* printf works the same in PHP and C.
* return works the same in PHP and C.
* The code ends with PHP destroying the output buffer and calling `main`.

## Open questions

> Why?

Why not?

> Hey, you did a stack allocation of point in C!

Yeah. I'm working on some different approaches. I've wanted to implement escape analysis for a long time, and started with it now. Stack alloc is safe if the memory does not escape scope. Regions could be an alternative for dynamic datastructures like linked lists. Also, just don't collect...? If the script is short-lived, it's "fine".

> Inheritance? Interfaces?

Maybe. I'll do function pointers in the struct first, passing self as first argument. But you can do inheritance in C with some tinkering.

> Mysql, curl, file access?

Did a test, can easily be done by wrapping C functions and applying PHP stubs. Memory might leak.

> kphp already exists.

[kphp](https://github.com/VKCOM/kphp) is a cool project, yes. :)

> Hiphop already exists

[HPHPc](https://en.wikipedia.org/wiki/HipHop_for_PHP) attempted to compile most of PHP's features, which limited the opportunities for performance gains. Also, ouch.

> PeachPie already exists

[PeachPie](https://en.wikipedia.org/wiki/PeachPie)? .NET, really?

> Current progress status of Pholyglot?

See the [test file](https://github.com/olleharstedt/pholyglot/blob/main/src/lib/test.ml). TODOs at the bottom.

> Use-cases?

Hm, maybe for simple REST calls that does not change often and have a high load? Performance can be 4x better for such IO tasks, I think (same as kphp). But it would require a FastCGI feature implemented.

Another use-case could be cronjobs that need to run fast, but CTO is not ready or willing to introduce a new language into the tech stack. Then a rewrite into Pholly could be easier to sell and do, perhaps? Assuming the transpiler would be more feature complete. Which will never happen.

One interesting milestone would be to run the [composer](https://getcomposer.org/) dependency resolver in Pholly. I don't think it'll ever happen, tho.

> Tech stack of the transpiler?

OCaml with Menhir, dune, opam.

**Unresolved issues**

PHP's `array`. Hard to type-infer. Could be a list, an actual C array (dynamic or fixed size), a tuple, or a hash table. Wrapping it in classes together with PHP class aliases could maybe be a way forward.

Memory, as noted above.
