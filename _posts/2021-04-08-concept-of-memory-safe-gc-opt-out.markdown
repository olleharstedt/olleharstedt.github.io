---
layout: post
title:  A concept for memory-safe GC opt-out with non-escaping variables
date:   2021-04-08
categories: programming
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/noescape.jpg" alt="y tho" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
*No escape for non-escaping variables*
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

There exists no language with memory-safe and painless opt-out of garbage collection that I know of. No language hits the 80/20 sweetspot, where 20% of an application _must_ be fast, but 80% does not. C# is on is way, with refs and value types. This article outlines a different concept.

TODO: Which domain has this requirement? Games, where you often combine two languages?

todo: tuple languages

## Memory

When you, the developer, write a variable and allocates memory, there are three basic scenarios:

* You know the scope _and_ the size
* You know the scope _but not_ the size
* You know _neither_ the size nor the scope

These scenarios can be dealt with the following strategies:

* Stack allocation
* Region (memory pool)
* Garbage collection

(The case when you know size but not scope is trivially solved by a global variable.)

Example use-cases:

* Bla
* Linked list
* Bla

Possible syntax:

```rust
// Not allowed to escape current scope
local point = new Point {10, 20};
let r = new Region;
// point2 is not allowed to escape the lifetime of r (same as stack allocation)
let point2 = new Point {20, 30} in r;
// Defaults to garbage collection; allowed to escape anywhere
let point3 = new Point {30, 40};
```

## Escaping

Escaping its life-time or scope.

Escape analysis usually used in the backend of the compiler, like in Go. Multiple optimizing techniques related to it, like stack allocation and scalar replacement.

Idea: To put escape analysis at the _front_ of the compiler, giving the programmer the opportunity to create variables that are disallowed to escape its scope, giving a compile-tiem warning if it does.

Example:

```c
Point* new_point() {
    Point p = {10, 20};
    return &p;  // Wrong, stack allocation escapes scope
}
```

To achieve this check, you need to track an alias graph of all variables. Lots of known algoriths for this in the literature and papers, and in compilers (but usually on IR, not on the syntax tree).

## References

No value types, everything is passed by reference like in Java.

## Implemenation

Can hack something in OCaml which compiles to C. Use a simple memory pool system and insert reference counting.

todo: cycles with ref count

## Other systems

No language I know of that painlessly lets you opt out or in to/from GC in a memory-safe manner.

Rust has Rc and Arc but always puts the burden of borrowing and lifetime annotations on the developer; there's no "don't care" modus.

Clean and uniqueness would be one alternative: [Uniqueness typing](https://clean.cs.ru.nl/download/happlytml_report/CleanRep.2.2_11.htm). (Not maintained.)

Cyclone has regions but is not memory safe.

## TODO

* Does not cover parallelization
