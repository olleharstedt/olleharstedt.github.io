---
layout: post
title:  Pipeline-oriented programming with non-blocking IO
date:   2020-08-03
categories: php
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/pipes.jpg" alt="Pipes" height="400px"/>
{: refdef}
{:refdef: style="text-align: center;"}
_One mock to rule them all_
{: refdef}

## Introduction

Read-compute-write pipeline, where each triple is a _stage_. Enforced on framework level; don't inject any object with side-effects. The side-effect factory produces promises that can be resolved concurrently when wanted.

Yield as async/await. Add new IO and return to logic. Yield always waits. Yield array of promises is concurrent.

Race-condition if IO 1 takes longer than IO 2 and IO 2 depends on 1 to be finished.

Redux-saga. Amphp.

No mocking needed. Only mock the _result_ of the resolved side-effects.
