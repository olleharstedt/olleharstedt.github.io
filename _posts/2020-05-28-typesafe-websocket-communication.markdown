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
  <td style="font-size: 50px; border: none;">”</td>
  <td style="border: none;"><p class="blockquote">a language where the only operations that one can execute on data are the ones that are condoned by the data's type. That is, if your data is of type X and X doesn't support operation y, then the language will not allow you to to execute y(X).</p></td>
  </tr>
</table>

In this post, I also assume it to be *strong* and *static*, that is, checked during compile time without giving the programmer the possibility to mess things up by casting something to anything (like to `void*` in C).

Why is type-safety good? Because it roots out several categories of errors.

Is type-safe serialization possible? No. I'm assuming only one type definition is used for communication between server and client, and that server and client are using the same source-code for serialize/deserialize.

## OCaml - 1-minute crash course

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

Recrusive functions are defined with `let rec ...` instead of just `let`.

Enums<sup><a href="#note1">1</a></sup> can be defined like this:

```ocaml
type my_enum =
  | One
  | Two
  | Three of string
  | Four of int
```

and `switch` is called `match` and is used like this:

```ocaml
match enum_value with
  | One     -> "you got one!"
  | Two     -> "two"
  | Three s -> "the string " ^ s (* concatenation is done with operator ^ *)
  | Four i  -> "the number " ^ (int_of_string i)
```

## Websockets

You probably already know what websockets are, but just to recap (from [Mozilla](https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API)), it's:

<table class="border">
  <tr>
  <td style="font-size: 50px; border: none;">”</td>
  <td style="border: none;"><p class="blockquote">a technology that makes it possible to open a two-way interactive communication session between the user's browser and a server</p></td>
  </tr>
</table>

Above all, it makes is possible for the server to send data to the browser without the browser needing to reload or poll the server. A typical use-case is a chat application.

Websockets communicate with [frames](https://noio-ws.readthedocs.io/en/latest/overview_of_websockets.html), as you will see below. A frame is simply a 

<table class="border">
  <tr>
  <td style="font-size: 50px; border: none;">”</td>
  <td style="border: none;"><p class="blockquote">header + application data. The frame header contains information about the frame and the application data. The application data is any and all stuff you send in the frame "body".</p></td>
  </tr>
</table>

All major browsers support websockets by now.

## Serialization

We want to be able to send any data between the server and the client. Websockets can send both blobs and strings, but we'll use serialization to strings.

There are a number of extensions to OCaml that lets you serialize types automatically. In this article I use [ppx\_deriving\_json](https://ocsigen.org/js_of_ocaml/3.6.0/manual/ppx-deriving) from the [js\_of\_ocaml](https://ocsigen.org/js_of_ocaml/3.6.0/manual/overview) project.

Consider the following datatype for messages:

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

and define functions that serialize and deserialize the type:

```ocaml
let to_json = [%to_json: message]
let of_json = [%of_json: message]
```

<div style='margin: 1em 3em;'>
  <table>
  <tr>
  <td style="border: none;"><span class='fa fa-icon fa-info-circle fa-2x'></span></td>
  <td style="border: none;">There's some weird syntax going on here, with <code>[@@ ...]</code> and <code>[% ...]</code>. These are syntax extensions, where libraries hook into the OCaml abstract syntax-tree to do all sorts of magic tricks, like, in our case, generating code to convert types to strings and back.</td>
  </tr>
  </table>
</div>

OK, let's run an example!

```ocaml
let _ =
  let my_message = Chat "Hey, what's up?" in
  let json = to_json my_message in
  print_endline json
```

This will output

```bash
$ <compile the thing and run it>
[0,"Hey, what's up?"]
```

As you can see, all type information is lost. That's why we collect all possible communication inside the `message` type in our program, so that no misunderstanding can happen between the server and the client.

## Server

Alright, we need both a server and a client to get our natively compiled type-safe chat web app to work. For the websocket server, there are a couple of libraries available for OCaml. The one I'm using here is pretty old, because I'm too lazy to setup TLS on my local machine, but the same principles apply to newer libs.

Our library defines the following datatype (enum, if you will) for websocket frames:

```ocaml
type frame =
  | PingFrame of string
  | PongFrame of string
  | TextFrame of string
  | CloseFrame of int * string
  | BinaryFrame
  | UndefinedFrame of string
```

So these are the cases we must take care of in our server. Note that `BinaryFrame` does not carry any data, simply because this frame is not implemented by the library.

The main function to take care of an incoming frame will then look like this:

```ocaml
let rec handle_client channel =
  let%lwt frame = channel#read_frame in 
  match frame with
    | PingFrame msg ->
      let%lwt _ = channel#write_pong_frame in
      handle_client channel
    | TextFrame text ->
      let response = "You wrote this: " ^ text in
      let%lwt _ = channel#write_text_frame response in
      handle_client channel
    | PongFrame msg ->
      (* Do nothing *)
      return ()
    | CloseFrame (status_code, body) ->
      channel#write_close_frame
    | BinaryFrame ->
        raise (Failure "BinaryFrame not implemented")
    | UndefinedFrame msg ->
        raise (Failure ("Undefined frame: " ^ msg))
```

Let's unpack this.

* First line defines a new (recursive) function called `handle_client`, which takes exactly one argument: `channel`.
* `let%lwt` is a monadic bind for the asynchronous library. If you don't know what that means, just ignore it for now.
* `frame` is read from the channel and used in the `match` expression<sup><a href="#note2">2</a></sup>.

**Asynchronous code in OCaml**

A websocket server must be multitasking, accepting connections from multiple sources.

Async, lwt

I use lwt.

## Client

todo

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

<sup id="note1">1. They are really called algebraic datatypes, but if you don't know what that is, just think of it as enums that can carry data.</sup>

<sup id="note2">2. Object methods are accessed with `#`. The dot operator is already used for records in OCaml.</sup>
