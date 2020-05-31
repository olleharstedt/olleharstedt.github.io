---
layout: post
title:  Type-safe websocket communication
date:   2020-05-28
categories: ocaml
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/russell3.jpg" alt="Deal with it" height="600px"/>
{: refdef}
{:refdef: style="text-align: center;"}
*Bertrand Russel, the inventor of [type theory](https://plato.stanford.edu/entries/type-theory/)*
{: refdef}

## Overview

This blog post describes how to do type-safe websocket communication in OCaml.

## Type-safety

What is type-safety? To quote [stackoverflow](https://stackoverflow.com/a/25157350/2138090), it's

<table class="border">
  <tr>
  <td style="font-size: 50px;">”</td>
  <td><p class="blockquote">a language where the only operations that one can execute on data are the ones that are condoned by the data's type. That is, if your data is of type X and X doesn't support operation y, then the language will not allow you to to execute y(X).</p></td>
  </tr>
</table>

but in this post, I also assume it to be *strong* and *static*, that is, checked during compile time without giving the programmer the possibility to mess things up by casting something to anything (like to `void*` in C).

Why is type-safety good? Because it roots out several categories of errors.

Is type-safe serialization possible? No. I'm assuming only one type definition is used for communication between server and client, and that server and client are using the same source-code for serialize/deserialize.

## OCaml

[OCaml](https://en.wikipedia.org/wiki/OCaml) is a language which puts high priority on type-safety. You can get a general short introduction [here](https://ocaml.org/learn/tutorials/). For the purpose of this blog article, let my just mention that variables are defined like this:

```ocaml
let x = 10 in
...
```

and functions like this:

```ocaml
let plus x y = x + y
```

and are applied like this:

```ocaml
let sum = plus 10 20 in
...
```

Note that no explicit typing is necessary (but possible) since OCaml is fully [type-inferred](https://en.wikipedia.org/wiki/Type_inference).

## Websockets

You probably already know what websockets are, but just to recap (from [Mozilla](https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API)), it's:

<table class="border">
  <tr>
  <td style="font-size: 50px;">”</td>
  <td><p class="blockquote">a technology that makes it possible to open a two-way interactive communication session between the user's browser and a server</p></td>
  </tr>
</table>

Above all, it makes is possible for the server to send data to the browser without the browser needing to reload or poll the server. A typical use-case is a chat application.

All major browsers support websockets by now.

## Serialization

We want to be able to send any data between the server and the client. Websockets can send both blobs and strings, but we'll use serialization to strings.

There are a number of extensions to OCaml that lets you serialize types automatically. In this article I use [ppx\_deriving\_json](https://ocsigen.org/js_of_ocaml/3.6.0/manual/ppx-deriving) from the [js\_of\_ocaml](https://ocsigen.org/js_of_ocaml/3.6.0/manual/overview) project.

Consider the following algebraic datatype<sup><a href="#note1">1</a></sup> for messages:

```ocaml
type message =
  | Ping
  | Chat of string
```

We can automatically generate the serialization functions by adding `[@@deriving json]`:

```ocaml
type message =
  | Ping
  | Chat of string
[@@deriving json]
```

serialize object

same definition on both client and server

how can we be sure deserialization is correct?

test vs type safe

js class on both server and client, JSON.stringify -> no class, only object?

typescript, Any type? Cast to class

ppx\_deriving\_json a customized parser for each type

protobuff?

https://medium.com/@aems/one-mans-struggle-with-typescript-class-serialization-478d4bbb5826

typed json: https://www.npmjs.com/package/typedjson

```ocaml
type websocket_message = One | Two of int | Three of string
[@@deriving json]
```

## 5. Notes

<sup id='note1'>If you don't know what algebraic datatypes are, just think of it as enums that can carry data.</sup>