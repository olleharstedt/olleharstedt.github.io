---
layout: post
title:  Simple loop examples in Forth
date:   2023-11-19
categories: programming forth loop looping
---

Simple example using `begin` and `again`:

```
: loop ( i j -- )
    begin
    .s cr   \ Dump the stack for debugging purpose
    2dup    \ Duplicate i and j, since = will eat them
    = if    \ Compare i and j and put result at top of stack
        2drop   \ If i == j, cleanup and exit
        exit
    endif
    1+      \ Increase j with one
    again   \ Jump back to begin
```

**Sources**

http://www.figuk.plus.com/webforth/En/TutorlT.htm
