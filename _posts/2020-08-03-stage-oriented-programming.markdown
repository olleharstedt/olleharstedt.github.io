---
layout: post
title:  Stage-oriented programming with default non-blocking IO
date:   2020-08-03
categories: php
---

## Introduction

Read-compute-write pipeline, each one is a stage. Enforced on framework level; don't inject any object with side-effects.

Yield. Add new IO and return to logic. Yield always waits. Yield all at the end instead?

Race-condition if IO 1 takes longer than IO 2 and IO 2 depends on 1 to be finished.

Redux-saga. Amphp.

No mocking needed. Example.

Concurrency, async/await.
