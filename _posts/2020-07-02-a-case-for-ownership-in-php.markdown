---
layout: post
title:  A case for ownership semantics in PHP
date:   2020-07-02
categories: php
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/scarydoor.jpg" alt="Scary door" height="300px"/>
{: refdef}

Imagine you want to open a file. But you can't. Because it has already been opened. Do'h! Runtime error.

Static typing exists to move errors from runtime to compile time. It also serves to bridge the knowledge between what the developer knows about the program, and what the program knows about itself (encoded in its types).

An example. You might declare a float value to hold a currency. For the program it's still a float, but for the developer it's a currency value. Same goes for strings and emails, strings and phone numbers, and so on. You can use value objects to add more information to the program, e.g. a class `Email` with only one property.

One category of information that is still eluded in many type-systems is the order in which something should happen. A file must be opened, then read from, then closed. It cannot be first opened, then closed and then read from. The developer knows this, but the program - just as with the email and currency examples - has no idea.

The protocol of a class can be encoded in something called _typestates_. Instead of being just a file, it's know a file with state open or closed, and this state is part of its type.
