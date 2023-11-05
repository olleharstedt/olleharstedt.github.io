---
layout: post
title:  How do I write a prefix word in Forth?
date:   2023-10-19
categories: programming forth gforth
---

In my case, the correct solution was "parsing words". I'm just not familiar enough with the Forth terminology, but managed to find it.

Using gforth:

```forth
: foo parse-name ;  ok
foo bar .s <2> 94421291333284 3  ok 2
type bar ok
```

More info here: https://www.complang.tuwien.ac.at/forth/gforth/Docs-html/The-Input-Stream.html
