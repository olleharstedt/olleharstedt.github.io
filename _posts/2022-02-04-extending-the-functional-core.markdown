---
layout: post
title:  Extending the functional core
date:   2022-01-27
categories: programming
---

## Intro

Pre-reqs:

* Read about functional core, imperative shell architecture
* Command object pattern
* Purity, referential transparency, side-effects

Web dev, PHP, but applicable to other dynamic scripting langs.

This is theoretical and not intended as a finished concept.

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

## que()

`que()` is a queue function for side-effects that can happen "anytime" during a request run.

This only works when you DO NOT need the result of the operation, at all. Possibly only throw exception at failure.

## seq()

Each next closure is only executed if previous one returns `true`.

## run()

`run()` is used when you immediately need the result of a side-effect. This is an alternative to mocking objects and inject dependencies. Instead you configure `run()` by giving it a configuration object, saying what to return for each call during unit testing.