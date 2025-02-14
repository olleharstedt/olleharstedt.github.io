---
layout: post
title:  Wrap a DSL around PHPUnit to reduce boilerplate
date:   2024-11-19
categories: programming php dsl phpunit
---

DRAFT

Use strategy pattern to inject new keywords into the DSL parser class. Each keyword is responsible for its own evaluation.

Load new keywords this way using a `(load lib.php)` statement.

**Basic syntax and semantics:**

* First item in list is function, rest is arguments
* Application is using space, as in `(+ 1 2)`, or `(f x y)`
* Strings use quotation `"`
* Numbers are integers and float
* Line-comments use `;`

Primitive keywords needed:

* `load` for loading keywords defined as PHP classes
* `setq` to set variables
* 'concat' to concatenate strings

Example:

```
(test-class remotecontrol_handle
  (test-method add_participants
      (set (participant (list (map ("firstname" "John")))))
      (set (surveyId 1))
      (set (sessionKey "abc123"))
      (arguments sessionKey surveyId participant 'false)
      (result (list (map ("firstname" "John")))))
)
```

```
<?php
return new KeyWord(function($sexpr) {
    // eval
});
```
