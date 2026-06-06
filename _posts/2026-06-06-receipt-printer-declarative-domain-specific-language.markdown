---
layout: post
title:  Declarative DSL for receipt printer
date:   2026-06-06
categories: programming DSL
---


This is a pretty fun exercise:

Write a DSL that works as a template engine for a receipt printer

Evaluators should be able to output JSON, raw text, XML, or whatever (one evaluator class per output format, that is)

The input to the eval() is the DSL template and a receipt data object

There should be a plugin event before eval() so plugins can process the data object

Maybe (a subset of) HTML could be that DSL actually. Or not, it would have to support loops, for the receipt items. 

There are certain pros and cons with a declarative DSL vs a fluid interface.

How fluid can it really be when PHP is the host language?
