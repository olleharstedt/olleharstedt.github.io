---
layout: post
title:  Part 3 - Basic type-inference
date:   2015-08-12
categories: subsetphp
---

Hi!

In my last post I had managed to bootstrap the lexer and parser from Hack/HHVM. Since then I've been working on adapting a Hindler-Milner type-inference implementation to PHP. It now works for addition and strings, so

{% highlight php startinline=True %}
function foo($a) {
  return $a + 10;
}

$b = foo('oy');
{% endhighlight %}

will give you a type error: "expected number but got string" (I hope to setup a web page soon where one can try this out).

Some challenges that required extra thought were:

1. Scope. PHP has only function scope. The languages that traditionally use Hindler-Milner type-inference, like Haskell and ML, introduce a new scope for each variable binding ("let x = 10 in ...").

2. Function interfaces. In static languages like C, you can declare a function signature before you implement it, which let you write your implementations in any order. In OCaml, on the other hand, you always write your functions before you use them. In a dynamic language like PHP, this problem doesn't exist until runtime. So how do we solve this if we want to make PHP statically typed? Some alternatives are:

    Force declaration of functions in order of use
    Sort functions before you type-infer them
    Use C-like header-files to declare function types

In any way, mutual recursive functions might not be possible, not without header-files or specific key-words.

I've also been reading about LLVM and even wrote some code to generate their IR (intermediate representation). It compiled, but is trivial so far. I need to compile the PHP runtime and see if I can interact with it, add a garbage-collector, and so on. More about that in my next post (one month from now, I would presume).

Bye!

Olle
