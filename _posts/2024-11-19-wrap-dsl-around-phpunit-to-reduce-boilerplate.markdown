---
layout: post
title:  Wrap a DSL around PHPUnit to reduce boilerplate
date:   2024-11-19
categories: programming php dsl phpunit
---

DRAFT

Use strategy pattern to inject new keywords into the DSL parser class. Each keyword is responsible for its own evaluation.

Load new keywords this way using a `(load lib.php)` statement.

Example:

```
<?php
return new KeyWord(function($sexpr) {
    // eval
});
```
