---
layout: post
title:  What is a good function?
date:   2021-04-13
categories: programming
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/lambda.png" alt="Good function" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
*Lambda*
{: refdef}

<div style='margin: 1em 3em;'>
<table>
<tr>
<td><span class='fa fa-icon fa-info-circle fa-2x'></span></td>
<td>
This is a technical blog post about PHP and programming.
</td>
</tr>
</table>
</div>

Functions are one of the fundamental building blocks of programming, so it's important to get it right. It sounds easier than it is in a big and long project with many contributors, and when the domain complexity increases, you might end up with long functions, mixing processing and database access, functions which don't compose easily or at all. Different programmers will add new arguments as time goes by. In one word, it gets messy.

Following some of these basic guidelines might protect you from getting into the mess. To get out of a mess, to refactor big functions, is another topic.

## Size

The easiest quality of a good function is size: a good function is fairly short. NASA says 60 lines, or what can be printed on one screen. Others might say 60 lines is too big and would be easy to refactor, but few people would agree longer is good.

A function should not have too many arguments. If you have five or more, you can probably create a class instead, for example using the command object pattern. Having many arguments makes it hard to express the relationship between input and output.

todo, do one thing, but what is "one thing"? a single task that's easy to express, but a "unit of work" can sometime be easier to express as a command object, e.g. a database migration.

## Contract

Every function written has an explicit or implicit contract with its environment. A contract is defined by a pre-condition - what must be true before the function can successfully execute; and a post-condition - what will be true after the function has successfully returned. The post-condition can only happen if the pre-condition is fulfilled.

If the pre-condition is not fulfilled, there are at least two ways to abort:

* Failed assertion
* Throw an exception

**Assertions** should be used for internal invariants, things that should "never" happen or are expected by the programmers to always be upheld. One example is to check the string length given to a translation function - it can be asserted to always be at least one (translating an empty string is considered a bug). Assertions are disabled in production code.

**Throwing exceptions** happen when the interaction with the outside world goes awry. Maybe a database connection is no longer active, or the file system cannot be accessed. Another formulation is that it's correct to throw exception when it's impossible to fulfill the post-condition even when the pre-condition was fulfilled.

Use both assertions and exception to make sure all your input is correct before starting to operate. If you fail early, it will make the code easier to debug.

todo, "string" not good, "int" not good, should be more precise, might be better with wrapper classes (type alias in non-PHP), like Email or Weight instead of string and float, to reduce the risk of confusing different units.

**Unit-tests** should be used to make sure the contract of a function remains intact, even after a long time and after multiple people have changed the code. Test both the happy path and failures; it's important that every function fails correctly.

Returning null can be a normal part of the post-condition, for example when searching for a file that doesn't exist.

## State

todo, no state, except maybe cache or others that preserve referential transparency

do not access global state, "spooky action at a distance", can pass explicit state or make a class instead

## Side-effects

todo, referential transparency, query-command split, functional core

## Composition

todo

## Name

todo, verb + noun

no "and", compose instead

## Documentation

todo, one sentence to describe the relation between input and output

docblock

docs inside the function?

## Risk

All this being said, there's a limit to how pedantic or how much rigor you can apply in a business setting. Your risk analysis will decide your priorities - always start working with the high-risk items.
