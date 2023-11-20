---
layout: post
title:  How do I write a map function in Forth?
date:   2023-11-20
categories: programming forth functional
---

TODO: DRAFT

```forth
create arr 4 , 10 , 20 , 30 , 40 ,  \ Create an array of size 4 and store four numbers on it
 
: count ( A -- A+cell N ) 
    dup cell+ swap @ 
    ;
: map ( A N xt -- )
  >r begin dup while over r@ execute 1- swap cell+ swap repeat 2drop r> drop ;
: @.  @ . ;
: 1+! ( addr -- )  dup @ 1+ swap ! ;
 
arr count ' @. map
arr count ' 1+! map
cr
arr count ' @. map
```
