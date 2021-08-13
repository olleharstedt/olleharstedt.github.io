---
layout: post
title:  Unpacking separation of concern
date:   2021-08-07
categories: programming
---

DRAFT

<div style='margin: 1em 3em;'>
<table>
<tr>
<td><span class='fa fa-icon fa-info-circle fa-2x'></span></td>
<td>Unpacking, by which I mean, elaborated on, "discovered anew".</td>
</tr>
</table>
</div>

First and foremost: What is "separation" and what is a "concern"?

Wikipedia def of concern: 

> A concern is a set of information that affects the code of a computer program.

The page does not define "separation", but they write:

> Encapsulation is a means of information hiding.

In web development, we usually deal with these concerns of the code:

* IO (database and file access, writing HTML or JSON or XML to stdout, logging to stderr)
* Business logic and calculation
* Organization code, "glue code"

And these are the ways we can separate things:

* Split into multiple classes (using interfaces or inheritance)
* Split into multiple functions
* Split into multiple modules or libraries (or files, perhaps)
* Split into multiple services

Separation needs communication. In PHP-land, mostly these types:

* Function arguments
* Message passing between objects (method calling, slightly different than function calling due to inheritance)
* In the case of microservices, a message broker or HTTP request (REST etc).

Other languages have different tools of separation and communication, compare e.g. with Erlang where you split your "things" into multiple processes.

The most naive interpretation would be to put IO in one class, logic in one and organization in another, and then continue to split things depending on type of IO, type of logic, etc. A pretty natural way to code, I guess?

Are "data" and "behaviour" two different concerns? That depends on if they change together. Data/behaviour that changes together belongs together (kind of interpreting Robert Martin's "A class should have only one reason to change" in a looser way).

Instead of "data" and "behaviour", you can instead divide code by being pure and effectful. Then purity becomes the concern by which you should separate, putting IO in one module and pure classes/functions in another. Related is the "functional core, imperative shell" architectural pattern.

Here's one catch: The more files you touch in a change request, the higher the fault prediction is. Would "isolate change" be a better rule of thumb than "separation of concerns"? Or is "separation of concern" just a rephrasing of enforcing abstraction layers?

The quality attribute "changeability" (or maintainability) is highly related to this principle. To be able to easily change a piece of code, the programmer needs to be able to read, understand and edit the code _without_ affecting parts of the program they didn't expect. This in turn requires contracts to be fulfilled between the different parts of the code base (contracts checked statically or by tests). Maybe separation in this context can be understood as coupling and dependencies? Or, separation as sound communication between parts, again repeating the "encapsulation" property from above.

The million dollar question: Which type of separation is best for which type of concern? Or phrased differently, which type of separation isolates change best?

Is there a connection between interfaces and separation of concerns? Well, interfaces makes it possible to make parts interchangeable, increasing decoupling. One could say decoupling is _more_ or _better_ separation than tight coupling.

For the PHP language itself, we can ask if we have all the separation tools we need or would want, what is available in other languages or what is being researched on ("readonly" comes to mind, recently merged to 8.1, and the package internal access level in Java and Swift).
