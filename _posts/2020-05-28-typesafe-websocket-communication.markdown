---
layout: post
title:  Type-safe websocket communication
date:   2020-05-28
categories: ocaml
---

{:refdef: style="text-align: center;"}
![Bertrand Russel]({{ site.url }}/assets/img/russel.jpg)
{: refdef}
{:refdef: style="text-align: center;"}
*Bertrand Russel, the inventor of [type theory](https://plato.stanford.edu/entries/type-theory/)*
{: refdef}

## Overview

This blog post describes how to do type-safe websocket communication in OCaml.

## Type-safety

What is type-safety? To quote [stackoverflow](https://stackoverflow.com/a/25157350/2138090), it's

> a language where the only operations that one can execute on data are the ones that are condoned by the data's type. That is, if your data is of type X and X doesn't support operation y, then the language will not allow you to to execute y(X).

but in this post, I also assume it to be *strong* and *static*, that is, checked during compile time without giving the programmer the possibility to mess things up by casting something to anything (like to `void*` in C).

Why is type-safety good? Because it roots out several categories of errors.

## Websockets

You probably already know what websockets are, but just to recap (from [Mozilla](https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API)), it's

> a technology that makes it possible to open a two-way interactive communication session between the user's browser and a server,

Above all, it makes is possible for the server to send data to the browser without the browser needing to reload or poll the server. A typical use-case is a chat application.

All major browsers support websockets by now.

## Sending data


