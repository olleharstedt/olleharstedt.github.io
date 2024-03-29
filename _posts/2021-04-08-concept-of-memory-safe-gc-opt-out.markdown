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

There exists few languages with memory-safe and painless opt-out of garbage collection that I know of. No language really hits the 80/20 sweetspot, where 20% of an application _must_ be fast, but 80% does not. Rust and C are designed for code that must be fast 99% of the time. C# is on its way towards GC opt-out, with refs and value types. D has unsafe opt-out. This article outlines a different approach based on non-escaping variables.

## Locality kinds

When you, the developer, write a variable and allocate memory, there are three basic scenarios:

* You know the scope _and_ the size
* You know the scope _but not_ the size
* You know _neither_ the size nor the scope

(TODO: Difference between known size and known upper bound; what's the diff between upper bound and average usage in, say, A\*?)

(Scope is bound to lifetime.)

These scenarios can be dealt with the following strategies:

* Stack allocation
* Region (memory pool)
* Garbage collection

(The case when you know size but not scope is solved by a global static variable.)

Example use-cases, respectively:

* Data-transfer object, like user data
* Tree data-structure, like with A\* algorithm or abstract syntax-tree
* Web server data, like cached pages

Use-cases for non-growing vs growing regions:

* Cache
* IO buffer (TODO: elaborate; fragmentation)

You end up with three different _locality kinds_:

* Stack-local
* Region-local
* Non-local

## Escape analysis

[Escape analysis](https://en.wikipedia.org/wiki/Escape_analysis) is a technique used in many different compilers, like Go and Java. It's the basis of different optimizations, for example stack allocation and scalar replacement. The idea is to track which variables escape scope or not, under the assumption that if they do not escape, they can be stack allocated instead of heap allocated, or replaced entirely.

Escape analysis is usually applied opportunistically, meaning that escaping (unknown lifetime) is the default and the compiler will check and apply optimizations when possible. To enforce locality kinds at compile-time, you put escape analysis at the _front_ of the compiler, giving the programmer the opportunity to create variables that are disallowed to escape their scope.

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

To achieve this feature, you need to check an alias graph of all variables for each function. There are lots of algorithms for this in the literature, papers and in compilers (but usually on the intermediate representation, not on the syntax tree).

## Possible language design

The three locality kinds can be expressed with:

* `local` keyword
* `let x = ... in r` and `foo() with r` for region allocation and passing
* Reference counted is the default

User-defined types can have any locality; the locality is not specified in the type.

```c++
struct Point {
    int x;
    int y;
};
```

Each variable will have both a type and a locality (assuming it's inferred here):

```c++
// Not allowed to escape current scope
local point = new Point {1, 2};
let r = new Region;
// point2 is not allowed to escape the lifetime of r
let point2 = new Point {3, 4} in r;
// Defaults to garbage collection; allowed to escape anywhere
let point3 = new Point {5, 6};
```

Note how `local` propagates the stack locality through the line:

```c++
// Two stack-allocated points and a stack-allocated array
local points = [new Point {1, 2}, new Point {2, 3}];
```

Locality must be part of function signature so that the calling function can know if the arguments escape or not. Left out locality defaults to non-local and garbage collected.

```c++
void add_to_point(local Point p1, local Point p2) {
    p1.x += p2.x
    p1.y += p2.y;
    // return p1 or p2 would be invalid here
}
```

Regions are passed to functions with `with`:

```c++
reg Point new_point() with r {
    let p = new Point {1, 2} in r;
    return p;
}
int main() {
    r = new Region;
    let p = new_point() with r;
}
```

(You could pass multiple regions this way.)

It would not be allowed to return regions:

```c++
Region new_region() {
    r = new Region;
    // Compile-time error: Regions are bound to function scope lifetime
    return r;
}
```

todo, copy, clone

Note that this allows both aliasing and mutability in a memory-safe manner.

todo: cycles with ref count

In summary:

* Three locality kinds for stack-, region and non-locality
* Pass-by-reference
* No implicit copy
* Mutation and aliasing allowed

## TODO

* Does not cover parallelization
* Need to outline rules for interaction between locality kinds, e.g. heap pointing to stack is OK but not other way around
* Interaction with C
* Feedback to check the integrity of the concept
* Proof-of-concept implementation with a couple of benchmarks
* Forget about the project forever :)
