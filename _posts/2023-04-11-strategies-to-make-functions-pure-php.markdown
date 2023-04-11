---
layout: post
title:  Strategies to make functions pure in PHP
date:   2023-04-11
categories: programming php
---

DRAFT

Move read from before

Defer write with yield

Defer write with command class. `__destructor` from SO.

Defer + exception = :( Running $refer class in shutdown function not that fun, especially since you don't know what went wrong.

Defer with lambda, can't check what's happening in test. Effect class - check its name.

When write depends on result from read, generate an AST (tagless-final)

AR vs repository pattern?

Capabilities.

Psalm and enforcing interfaces.

Easier to allow for mocking? When is purity better than mocks?

Diminishing return.
