---
layout: post
title:  How do I write a map function in Forth?
date:   2023-11-20
categories: programming forth functional
---

TODO: DRAFT

**Introduction**

Short example of how to create an FP-like `map` word in Forth.

**Acknowledgments**

GeDaMo and others on Libera IRC `##forth` channel.

```
\ Create an array of size 4 and store four numbers on it
create arr 4 , 10 , 20 , 30 , 40 ,
 
: count ( A -- A+cell N ) 
    dup     \ Duplicate address of array
    cell+   \ Move forward one cell
    swap    \ ? 
    @ 
    ;

: map ( A N xt -- )
  >r    \ Put xt on return stack?
  begin
    dup
    while
        over
        r@
        execute
        1-
        swap
        cell+
        swap
    repeat
    2drop
    r>
    drop
    ;

: @.  @ . ;

: 1+! ( addr -- )
    dup     \ Duplicate addr because why?
    @       \ Fetch content in address
    1+      \ Increase top of stack with 1
    swap    \ Swap to have addr on top, because store expects it
    !       \ Store new value to addr
    ;
 
arr count ' @. map
arr count ' 1+! map
cr
arr count ' @. map
```
