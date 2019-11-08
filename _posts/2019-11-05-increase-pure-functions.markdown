---
layout: post
title:  How to increase the number of pure functions in your code-base
date:   2019-11-05
categories: opinion
---

{:refdef: style="text-align: center;"}
![Purity]({{ site.url }}/assets/img/purity.jpg)
{: refdef}

This blog post argues from the premise that more pure functions will increase your code quality, _provided_ that such refactorization does not violate common sense coding habits like function LoC, no more than five arguments per function, etc.

Pure functions can be written in different ways: as loose functions, in modules or in classes without state. It all depends on which programming language you use, but no matter which language, you have to make a choice about how to deal with side-effects (or you choose not to choose). This is true for Python, Haskell, C, and all other languages out there.

Pure functions have lots of desirable properties, for example:

* Composability (without side-effects, you can combine them freely)
* Predictability (clear relation between input and output)
* Don't need any mocking (all dependencies are in the input variables)
* Don't need to be mocked (since mocking is mostly done to control side-effects, pure functions can often be used as-is)

Because of this, we want to increase the number of pure functions in our code-base and reduce the number of functions and classes that have side-effects, or at leats make sure pure and stateful code are clearly separated. But how? Here are some ways:

* Move side-effects up in the stack-trace to separate calculations from file IO, database access, etc (e.g. only call classes with side-effects from controller methods if using MVC)
* Use value objects with no methods and no setters instead of traditional OOP; these objects are also immutable (functional programming languages already provide records for this use-case)
* Use explicit state instead of implicit (kind of the same as above; state is part of function input and passed around)
* If a method has no this nor self, consider if it should be moved to a separate class or module

There are also some downsides:

* You cannot use inheritance for code reuse (BUT: Maybe better to use composition and interfaces instead anyway?)
* In an OOP language, code could look unorthodox and become harder to read and maintain, or seem contrived
* Depending on your domain, you might spend most of your time shuffling data from here to there, which is impure, so what's the point?

Some things to think about:

* Should a project's folder structure reflect pure vs stateful code?
* The SOLID principle states that a class should have only one responsibility. Does that imply that a class with stateful methods should not have pure methods, and vice versa?
* How would you educate colleagues about pros and cons of pure functions? Should we talk more about pure vs stateful code in the programming community?

Do you have any thoughts or something to add?

Thanks for reading!
