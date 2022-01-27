---
layout: post
title:  On composability in software design
date:   2022-01-27
categories: programming
---

Some short notes on composability to clear my thoughts.

This is not related to composable infrastructure.

* Code should either:<br/>
1) Organise composable parts; or<br/>
2) Be a composable part[Functional core, imperative shell]
* Functions are composable when they can be combined freely
```java
foo(bar())
foo(bar() + bar())
foo() - bar(baz())
```
* Classes are composable when they are interchangeable, using interfaces
```java
new Foo(new Bar())
new Foo(new Baz())
foo(new Bar() + new Baz())
```
* It's OK to not be composable if you're at the top of the stack trace, e.g. `commandObject.run()` or a controller method
```java
function run() {
    foo()
    bar(baz())
}
```
* Being composable means having a clear relation between input and output, preferably stated as a contract
```java
/** Returns an order object with recalculated vat based on country */
function recalculateOrder(order, country): order {
    // ...
}
```
* Side-effects (writing/reading to/from IO, file, database, etc) decrease composability, move them up in the stack trace when possible
```java
// Not good
function processOrders(orderIds) {
    orders = getOrders(orderIds)
    // ...
}
// Better, fetch at call site instead, like processOrders(getOrders(orderIds))
function processOrders(orders) {
    // ...
}
```
* Implicit dependencies decrease composability, make them explicit either with injection or as function arguments
```java
// Not good
function foo() {
    event = new Event("bar")
    // ...
}
// Better, like foo(new Event("bar"))
function foo(event) {
    // ...
}
// With an injected factory
function foo() {
    event = this.eventFactory.make("bar")
}
```
* Composable parts are easier to unit test
* Developing composable parts is like constructing a language[SICP]
* Code consisting of composable and interchangeable parts are easier to change, both for bug fixes and feature requests
* Writing to a class property decreases composability, prefer returning a value when possible
* You can never have 100% composability due to cross-cutting concerns
* There's no established code metric to measure composability
* When you have code clones, there are composable parts to factor out
* The expressiveness of a programming language sets limits on what you can combine and compose
* There's little or no empirical evidence related to composability and changeability.
