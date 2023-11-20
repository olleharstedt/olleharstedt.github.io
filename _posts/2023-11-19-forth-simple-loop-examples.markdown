---
layout: post
title:  Simple loop examples in Forth
date:   2023-11-19
categories: programming forth loop looping
---

Simple example using `begin` and `again`:

```
\ Loop until i = j
\ i must be higher than j
: loop ( i j -- )
    begin   \                                                   ( i j )
    .s cr   \ Dump the stack for debugging purpose              ( i j )
    2dup    \ Duplicate i and j, since = will eat them          ( i j i j )
    =       \ Compare i and j and put result at top of stack    ( i j result )
    if      \ Enter if-body if top of stack is 0                ( i j )
        2drop   \ If i == j, cleanup and exit                   ( )
        exit
    endif
    1+      \ Increase j with one                               ( i j+1 )
    again   \ Jump back to begin
```

**Sources**

http://www.figuk.plus.com/webforth/En/TutorlT.htm
