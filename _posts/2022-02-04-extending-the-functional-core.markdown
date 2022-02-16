---
layout: post
title:  Strategies for extending the functional core - delayed effect queue builder
date:   2022-01-27
categories: programming
---

## Intro

Pre-reqs:

* [Functional core, imperative shell architecture](https://github.com/kbilsted/Functional-core-imperative-shell/blob/master/README.md)
* Purity, referential transparency, side-effects

Rational:

* The functional core is more testable and more composable than the imperative shell or effectful code
* Sometimes you can easily extend the functional core by lifting out side-effects to calling code
* Sometimes, the side-effects are entangled inside business logic
* This blog post outlines a couple of strategies to extend what's considered "functional core" by wrapping side-effects in different categories of command objects or closures
* Another motivation is that mocking is often complex (and boring) to write, and reducing the need of mocking in testing will make your test suite simpler

The different categories of side-effects (or just "effects") in this article are:

* Effects that can be delayed
* Effects that depend on each other
* Effects where the result is needed at once

Use-case: A function to create x number of dummy users from a web request object, save them in database and show a result.

```java
function createDummyUsers(Request request, St st)
{
    times  = request.getParam("times", 5);
    dummyUsers = new Stack();

    for (; times > 0; times--) {
        user           = new User();
        user.username  = "John Doe";

        st.if(() -> user.save())
          .then(() -> dummyUsers.push(["username" => user.username]));
    }

    return {
        "success":    true,
        "dummyUsers": dummyUsers
    };
}

```

```java
user = new User();
// ... set properties
st(() -> user.save() ? void : throw new Exception("Could not save user"));
```

(An alternative is `st.throwOnFalse(fn () => user.save());`)

```java
st
  .if(() -> user.save())
  .then(() -> /* Do something */)
  .else(() -> /* Throw exception? */);
```

> But why?

To get rid of mocking and injection.  When writing the unit test, you can skip the `if` part and just run the `then` or `else` part to check the behaviour at success or failure. You don't even have to mock the state object `st`, just get the event queue and manipulate it however you want in the test code.
