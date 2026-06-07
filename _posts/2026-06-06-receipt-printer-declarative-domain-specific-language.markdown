---
layout: post
title:  Declarative DSL for receipt printer
date:   2026-06-06
categories: programming DSL
---

## Why?

This is a pretty fun exercise:

Write a DSL that works as a template engine for a receipt printer

Evaluators should be able to output JSON, raw text, XML, or whatever (one evaluator class per output format, that is)

The input to the eval() is the DSL template and a receipt data object

There should be a plugin event before eval() so plugins can process the data object

Maybe (a subset of) HTML could be that DSL actually. Or not, it would have to support loops, for the receipt items. 

There are certain pros and cons with a declarative DSL vs a fluid interface.

How fluid can it really be when PHP is the host language?

Should/must support partials?

## Printer payload

* Receipt data
* Language
* Currency
* Active campaigns
* Settings, like print logo
* Open drawer

## Receipt parts

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

## Elements and functions

* Line
* Header
* Center
* Bold
* Divider
* Cut
* Finals?
* Message
* Barcode
* Utf8ToCP865
* Utf8ToEpsonSE
* Round item unit (st, kg)
* Translate item unit (st, kg)
* Reset printer

## Base functions

* if
* switch?
* loop
* list or `'`

## Notes

```lisp
; Store info
(if settings.receipt_print_company_name
    '(
        (header store.store_name)
        linefeed
    ))
(center-line store.store_address)
```
