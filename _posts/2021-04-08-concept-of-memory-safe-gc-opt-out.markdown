---
layout: post
title:  A concept for memory-safe opt-out of GC with locality kinds
date:   2021-04-08
categories: programming
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/noescape.jpg" alt="y tho" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
*Oh noe*
{: refdef}

<div style='margin: 1em 3em;'>
<table>
<tr>
<td><span class='fa fa-icon fa-info-circle fa-2x'></span></td>
<td>This is just a concept. No implementation is made.</td>
</tr>
</table>
</div>

## Introduction

There exists few languages with memory-safe and painless opt-out of garbage collection that I know of. No language really hits the 80/20 sweetspot, where 20% of an application _must_ be fast, but 80% does not. Rust and C are designed for code that must be fast 99% of the time. C# is on is way towards GC opt-out, with refs and value types. D has unsafe opt-out. This article outlines a different approach based on non-escaping variables.

## Locality kinds

When you, the developer, write a variable and allocate memory, there are three basic scenarios:

* You know the scope _and_ the size
* You know the scope _but not_ the size
* You know _neither_ the size nor the scope

(Scope is bound to lifetime.)

These scenarios can be dealt with the following strategies:

* Stack allocation
* Region (memory pool)
* Garbage collection

(The case when you know size but not scope is solved by a global static variable.)

Example use-cases, respectively:

* Data-transfer object, like user data
* Tree data-structure, like with A\* algorithm
* Web server data, like cached pages

You end up with three different _locality kinds_:

* Stack-local
* Region-local
* Non-local

## Escape analysis

Escape analysis is a technique used in many different compilers, like Go and Java. It's the basis of different optimizations, for example stack allocation and scalar replacement. The idea is to track which variables escape scope or not, under the assumption that if they do not escape, they can be stack allocated instead of heap allocated, or replaced entirely.

Escape analysis is usually applied opportunistically, meaning that escaping (unknown lifetime) is the default and the compiler will check and apply optimizations when possible. To enforce locality kinds at compile-time, you put escape analysis at the _front_ of the compiler, giving the programmer the opportunity to create variables that are disallowed to escape its scope.

Example in C:

```c
Point* new_point() {
    Point p = {1, 2};
    Point* q = &p;
    // Runtime error, stack allocation escapes scope
    return q;
}
```

If this was done in Java or Go, it would allocated on heap instead of stack, _because_ it escapes. In a hypothetical language with locality kinds, it would be:

```c++
Point new_point() {
    // Stack-locality propagated to all constructors on right-side:
    local point = new Point {1, 2};
    // Aliasing is allowed
    local point2 = point;
    // Compilation error - not allowed to escape scope
    return point2;
}
```

To achieve this check, you need to track an alias graph of all variables. Lots of known algoriths for this in the literature and papers, and in compilers (but usually on IR, not on the syntax tree).

## Implemenation

The three locality kinds can be expressed like so:

```c++
// Not allowed to escape current scope
local point = new Point {10, 20};
let r = new Region;
// point2 is not allowed to escape the lifetime of r (same as stack allocation)
let point2 = new Point {20, 30} in r;
// Defaults to garbage collection; allowed to escape anywhere
let point3 = new Point {30, 40};
```

Note how `local` propagates the stack-local scope through the line:

```c++
// Two stack-allocated points and a stack-allocated array
local points = [new Point {1, 2}, new Point {2, 3}];
```

Locality must be part of function signature so that the calling function can know if it variables escape or not:

```c++
todo
```

Note that this allows both aliasing and mutability in a memory-safe manner.

Can hack something in OCaml which compiles to C. Use a simple memory pool system and insert reference counting.

todo: cycles with ref count

No value types, everything is passed by reference like in Java.

In summary:

* Three locality kinds for stack-, region and non-locality
* Pass-by-reference
* Mutation and aliasing allowed

## Other systems

No language I know of that painlessly lets you opt out or in to/from GC in a memory-safe manner.

Rust has Rc and Arc but always puts the burden of borrowing and lifetime annotations on the developer; there's no "don't care" modus (they had pre-1.0.0 release).

Clean and uniqueness would be one alternative: [Uniqueness typing](https://clean.cs.ru.nl/download/happlytml_report/CleanRep.2.2_11.htm). (Not maintained.)

Cyclone has regions but is not memory safe.

## TODO

* Does not cover parallelization or green threads
