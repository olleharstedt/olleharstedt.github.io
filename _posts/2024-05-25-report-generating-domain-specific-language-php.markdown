---
layout: post
title:  Report generating domain-specific language
date:   2024-05-25
categories: programming php
---

Why can't the customers write their own damn reports. Or the sales people. Or the technical support. They already know some basic SQL, right? How hard could it be to reate a safe subset of SQL, that also includes some HTML formatting capability? But the parser would have to be dirt simple. I don't want to en dup with a big pil eof code only I can maintain. Kind kind of languages work? S-expressions? Forth? JSON? It would have to be able to deal with complex calculations between database columns.

The formats I considered:

* S-expressions, because it can be lexed and parsed in a handful of lines
* Forth-like, for the same reason
* JSON, because it's common in web

There are no good lexer/parser libs to PHP that are actively maintained, sadly.

To be able to sell the solution to colleagues, the base system would have to be really simple.

The end result DSL should be able to blend, seamlessly:

* Structured data for HTML, CSS, SQL
* Logic in SQL, PHP and possibly JavaScript

Use-case: An article report
