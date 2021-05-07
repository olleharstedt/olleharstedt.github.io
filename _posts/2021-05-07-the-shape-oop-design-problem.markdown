---
layout: post
title:  The shape OOP design problem
date:   2021-05-07
categories: programming
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/brain.webp" alt="Dependencies" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
*Brain?*
{: refdef}

## Introduction

Imagine you have three shapes:

* Point
* Rectangle
* Circle

They have the following properties:

* Point: int x, int y
* Rectangle: Point bottomLeft, Point topRight
* Circle: Point center, int radius

Let's add two behaviour to this data: area() and draw().

Here's comes the design problem: Which behaviour belongs to which data, and why? Imagine you want to keep the following properties of the design:

* Cohesion: A class should be responsible for one thing only
* Coupling: You should be able to isolate change in the system
* Encapsulation: Classes shouldn't share internal representation
* Polymorphism: The design should be based on interfaces

In the end, you should be able to do something like:

```
forall shapes as shape, do shape.draw(surface)
```

Or if you move out the drawing logic:

```
forall shapes as shape, do surface.draw(shape.getDrawData())
```

Same behaviour should be possible for area calculation of a list of shapes.

Everyone has a different solution to this problem. In particular, solutions in OOP and FP looks very different.

After you finish the design, ask yourself these questions:

* How easy would it be to move to a 3D representation?
* How easy would it be to add a new shape, say, triangle?
* How easy would it be to add a new behaviour to all shapes, like save/load from a SQL database?

Beneath are two example implementations, one in PHP and one in OCaml.

## OCaml

```ocaml
type point = {x : int; y : int}
type rectangle = {bottom_left : point; top_right : point}
type circle = {center: point; radius : int}

type shape =
    | Point of point
    | Rectangle of rectangle
    | Circle of circle

let area_of_shape (s : shape) : int =
    match s with
        | Point p -> 0
        | Rectangle {bottom_left; top_right} -> 1
        | Circle {center; radius} -> 2

module Surface = struct
    type t
end

module type SHAPE = sig
    type t
    val area : t -> int
    val draw : t -> Surface.t -> unit
end

module Point : SHAPE = struct
    type t = {x : int; y : int}
    let area t = 0
    let draw t surface = ()
end

module Rectangle : SHAPE = struct
    type t = {bottom_left : Point.t; top_right : Point.t}
    let area t = 0
    let draw t surface = ()
end
```
