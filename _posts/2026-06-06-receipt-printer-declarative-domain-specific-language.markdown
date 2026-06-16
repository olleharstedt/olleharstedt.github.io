---
layout: post
title:  Declarative domain-specific language for receipt printer
date:   2026-06-06
categories: programming DSL
---

### What?

Write a declerative domain-specific langugae (DSL) that works as a template engine for a receipt printer. 

### Why?

An old printer class is decaying beyond any recognition. What used to be pure layout code is now littered with conditionals to decide padding and formatting, even when the injected printer driver class is used to distinguish between different printers.

> Just clean up the printer class?

I want it be be easier to switch layouts, edit layouts, and also really enforce the separation of template structure from fetching of data, processing of data, and support for different printers.

> Why not a fluid interface?

As soon as you need a little bit of logic in your template, the interface breaks down.

TODO Example

> Why not an existing template language, like XML, JSON? Or PHP?

It needs to be sandboxed to be safe, so any general programming language is out.

If you use HTML or HTML-like as a template language, you actually force yourself to use _two_ languages - one for document structure, and another for logic, like if-statements that almost occur in templates.

The logical primitives should not be a separate language from the structural primitives.

JSON does not support logic naturally.

### How?

[Previously](https://olleharstedt.github.io/programming/php/dsl/2024/05/25/report-generating-domain-specific-language-php.html) I've considered a [Forth](https://en.wikipedia.org/wiki/Forth_(programming_language))-like and [S-expressions](https://en.wikipedia.org/wiki/S-expression) as DSL, because they are (somewhat) established, and the lexer/parser can be tiny.

I recently stumbled upon a ["Scheme Request For Implementation"](https://srfi.schemers.org/srfi-49/srfi-49.html) about white-space in the Scheme language, which would make it possible to use S-expressions without spamming parentheses everywhere.

Instead of

```lisp
(if (= x 1)
  '(
    (print store.adress)
    (print newline)
    ))
```

you would have

```lisp
if (= x 1)
  begin
    print store.adress
    print newline
```

The [lexer/parser](https://en.wikipedia.org/wiki/Parsing) must be small, around 200 LoC. The evaluation of keywords must be pluggable using the [strategy design pattern](https://en.wikipedia.org/wiki/Strategy_pattern), to be able to output HTML, JSON, raw text, or whatever the physical printer expects.

Declarative means it should not be able to hold state or change state of injected variables; it should focus on the "what", not the "how". The "how" is encapsulated in the injected evaluator.

It needs to support:

* If-else-statements
* Loops
* Dot-notation for property access of template data, like `store.street_adress`

Could-haves:

* Partials, to be able to reuse templates between different layouts
* Changeable by non-programmers (like HTML or CSS)

### How?

Evaluators should be able to output JSON, raw text, XML, or whatever (one evaluator class per output format, that is)

The input to the eval() is the DSL template and a receipt data object

There should be a plugin event before eval() so plugins can process the data object

There are certain pros and cons with a declarative DSL vs a fluid interface.

How fluid can it really be when PHP is the host language?

Should/must support partials?

Event to add functions to the DSL.

**Printer payload**

* Receipt data
* Language
* Currency
* Active campaigns
* Settings, like print logo
* Open drawer

**DSL Features**

* Optional arguments with default values
* SRFI-49 I-expressions
* Dot-notation for property access
* if-else

**Receipt parts**

* Store info
* Corrected by, correcting
* Items (campaign, discount)
* Payments
* Footer
* Sign field
* Control code
* Transaction information, amount, card
* On hold
* Refund sign

**Elements and functions**

* Linefeed
* Header
* Center
* Bold
* Divider
* Cut
** TODO Not all printers have cut - if you print 2 receipts, you instead need a confirm popup before printing the second one, to give the clerk time to rip off the first receipt
* Finals?
* Message
* Barcode
* Utf8ToCP865
* Utf8ToEpsonSE
* Round item unit (st, kg)
* Translate item unit (st, kg)
* Reset printer

**Base functions**

* if
* switch?
* loop
* list or `'`

**Notes**

S-expression:

```lisp
; Store info
(if settings.receipt_print_company_name
  '(
    (header store.store_name)
    linefeed
  ))
(center store.store_address)
```

I-expression:

```lisp
; Store info
if settings.receipt_print_company_name
  header store.store_name
  linefeed
center store.store_address
```

TODO Forth-like

TODO I-expression

Using LLM to write the core part of the lexer/parser left me with zero sense of accomplishment.

TODO Who and when will the DSL be used and changed

TODO Maintenence and debugging will be hell. The tiny size of the lexer/parser will make it easier. On-boarding of new devs will become harder. But not more difficult than to an elaborate fluid interface? Lexer/parser systems should not be unknown to experienced developers.

**Questions**

> Isn't it easier to reuse layout data if you use inheritance, and a layout class instead of template?

Perhaps, but the code decay would be inevitable over time.

**Links**

https://srfi.schemers.org/srfi-49/srfi-49.html

https://sourceforge.net/p/readable/wiki/Rationale-sweet/

Design Guidelines for Domain Specific Languages https://arxiv.org/abs/1409.2378
