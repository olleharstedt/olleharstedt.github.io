---
layout: post
title:  Unpacking Separation of Concern
date:   2021-08-07
categories: programming
---

<div style='margin: 1em 3em;'>
<table>
<tr>
<td><span class='fa fa-icon fa-info-circle fa-2x'></span></td>
<td>Unpacking, by which I mean, elaborated on, "discovered anew".</td>
</tr>
</table>
</div>

First and foremost: What is "separation" and what is a "concern"?

Wikipedia def of concern: 

> A concern is a set of information that affects the code of a computer program.

The page does not define "separation", but they write:

> Encapsulation is a means of information hiding.

In web development, we usually deal with these three concerns:

* IO (database and file access, writing HTML or JSON or XML to stdout, logging in stderr)
* Business logic and calculation
* Organization code, "glue code"

And these are the way we can separate things:

* Split into multiple classes (using interfaces)
* Split into multiple functions
* Split into multiple modules or libraries
* Split into multiple services

Is "data" and "behaviour" two different concerns? That depends on if they change together. Data/behaviour that change together belongs together.

Other languages have different tools, compare e.g. with Erlang where you split your "things" into multiple processes.

Here's the catch: The more files you touch in a change request, the higher the fault prediction is.
