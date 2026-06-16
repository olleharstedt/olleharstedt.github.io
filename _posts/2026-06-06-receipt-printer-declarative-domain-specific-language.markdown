---
layout: post
title:  Declarative domain-specific language for receipt printer
date:   2026-06-06
categories: programming DSL
---

**What?**

Write a declerative domain-specific langugae (DSL) that works as a template engine for a receipt printer. The lexer/parser must be small, around 200 LoC. The evaluation of keywords must be pluggable using the [strategy design pattern](https://en.wikipedia.org/wiki/Strategy_pattern), to be able to output HTML, JSON, raw text, or whatever the physical printer expects.

Declarative means it should not be able to hold state or change state of injected variables.

It needs to support:

* If-else-statements
* Loops
* Dot-notation for property access of template data, like `store.street_adress`

Could-haves:

* Partials, to be able to reuse templates between different layouts
* Changeable by non-programmers (like HTML or CSS)

**Why?**

An old printer class is decaying beyond its original intent. What used to be pure layout code is no littered with conditionals, even when the injected printer driver class is used to distinguish between different printers.

> Why not a fluid interface?

As soon as you need a little bit of logic in your template, the interface breaks down.

TODO Example

> Why not an existing template language, like HTML? Or PHP?

It needs to be sandboxed to be safe.

If you use HTML or HTML-like as a template language, you actually force yourself to use _two_ languages - one for document structure, and another for logic, like if-statements that almost occur in templates.

**How?**



---

Evaluators should be able to output JSON, raw text, XML, or whatever (one evaluator class per output format, that is)

The input to the eval() is the DSL template and a receipt data object

There should be a plugin event before eval() so plugins can process the data object

Maybe (a subset of) HTML could be that DSL actually. Or not, it would have to support loops, for the receipt items. 

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

**Questions**

> Isn't it easier to reuse layout data if you use inheritance, and a layout class instead of template?

Perhaps, but the code decay would be inevitable over time.

**Links**

https://srfi.schemers.org/srfi-49/srfi-49.html

https://sourceforge.net/p/readable/wiki/Rationale-sweet/

Design Guidelines for Domain Specific Languages https://arxiv.org/abs/1409.2378
