---
layout: post
title:  How do I use the vocabulary in Forth?
date:   2023-10-19
categories: programming forth gforth
---

Using gforth.

    vocabulary my-voc  \ Create new vocabulary my-voc
    my-voc also        \ Add my-voc to wordlist order
    definitions        \ Use my-voc as current compilation wordlist
    order              \ List wordlist order
    \ In GForth, this will output: order my-voc my-voc Forth Root     my-voc  ok
    : test s" Hello test" type cr ;  \ Create new word in current wordlist
    test               \ Prints "Hello test"
    previous           \ Pops the top wordlist from the search order list
    previous           \ Need to pop my-voc twice?
    test               \ error: Undefined word
    my-voc also        \ Add wordlist again
    test               \ Prints "Hello test"
    
Not using the word `also` will add _only_ that wordlist to the search order:

    my-voc
    words   \ Lists only "test"
    :       \ error: Undefined word

Sources:

https://astro.pas.rochester.edu/Forth/words.html

https://www.complang.tuwien.ac.at/forth/gforth/Docs-html/Word-Lists.html#Word-Lists
