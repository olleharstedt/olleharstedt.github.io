---
layout: post
title:  Wrap a DSL around PHPUnit
date:   2024-11-13
categories: programming php dsl
---

DRAFT

```lisp
; PHP Unit test
(test-class remotecontrol_handle
  (test-method add_participants
      (set (participant (list (map ("firstname" "John")))))
      (set (surveyId 1))
      (set (sessionKey "abc123"))
      (arguments sessionKey surveyId participant 'false)
      (result (list (map ("firstname" "John")))))
)
```
