---
layout: post
title:  A memory-polymorphic, polyglot implementation of SplDoublyLinkedList (as transpiled by Pholyglot 0.2-betachicken)
date:   2023-03-19
categories: programming
---

<style>
h4, h3 {
  display: none; /* hide */
}
h4 + p {
    padding: 10px;
    background-color: rgb(221, 244, 255);
    margin: 10px;
    color: #333;
}
h3 + p {
    padding: 10px;
    background-color: #fff8c4;
    margin: 10px;
    color: #333;
}
</style>

### Warning
**&#x26a0;** DRAFT

[Pholyglot](https://github.com/olleharstedt/pholyglot) is a [transpiler](https://en.wikipedia.org/wiki/Source-to-source_compiler) that compiles a subset of PHP into PHP-and-C compatible code, so called [polyglot code](https://en.wikipedia.org/wiki/Polyglot_(computing)).

This blog post describes the new features added into version 0.2 of the Pholyglot transpiler:

* Two different memory allocation strategies
* A memory-polymorph linked list

**Memory allocation**

One of the reason I started this project was to experiment with an opt-out-of-GC kind of system. In Pholyglot, the [Boehm GC](https://en.wikipedia.org/wiki/Boehm_garbage_collector) will be used as default, but you can now also choose to use an [arena](https://stackoverflow.com/a/12825221/2138090). The interaction between these two memory systems in the same program is not safe yet, but the idea is to add alias control and escape analysis to enforce a clear separation.

**Memory-polymorphism**

Not sure if this is an established word, but what it means in Pholyglot is that you can tell an object to use the same allocation strategy as another object, without knowing exactly which strategy was used.

This example adds a new `Point` to a list of points, using the same memory-strategy as the list, using a new type of annotation `@alloc`:

```php
/**
 * @param SplDoublyLinkedList<Point> $list
 */
function addPointToList(SplDoublyLinkedList $list): void
{
    // Use same memory allocation strategy as $list
    $p = /** @alloc $list */ new Point();
    $list->push($p);
}
```

Obviously, at a later stage, `$list->push($p)` must be type-checked so that two different memory strategies aren't being used in the same collection.

The above snippet compiles to this[^1] (and yes, this is valid vanilla PHP):

```c
#define function void
function addPointToList(SplDoublyLinkedList $list)
#undef function
{
    #__C__ Point
    $p = new(Point
        #__C__, $list->mem
    );
    $list->push(
        #__C__ $list,
        $p
    );
}
```

where `new` is a macro taking two arguments: the object and a memory allocation strategy struct:

```text
#define new(x, m) x ## __constructor((x) m.alloc(m.arena, sizeof(struct x)), m)
```

`m` is defined as:

```c
struct mem {
    uintptr_t* (*alloc) (void* a, size_t size);
    void* arena;
};
```

Meaning, it contains a pointer to an allocation function (currently to either the Boehm GC alloc or arena alloc), and a pointer to the arena (not used for Boehm).

I hope this makes sense. :)

Other possible memory strategies could be `unsafe` that just mallocs and never frees (possibly useful for global variables); `malloc` that mallocs and is not allowed to escape scope (because it's freed at end of scope); or `stack` that allocs on the stack instead of heap, and is also not allowed to escape. I've written more about my thoughts [here](http://127.0.0.1:4000/programming/2021/04/08/concept-of-memory-safe-gc-opt-out.html).

A full example with points and lists can be found in [this gist](https://gist.github.com/olleharstedt/228f6e4a5ccf3ee97b7ab3f83362873c).

**Notes**

[^1]: The `#__C__` word is removed before compiling with `gcc` using `sed`.
