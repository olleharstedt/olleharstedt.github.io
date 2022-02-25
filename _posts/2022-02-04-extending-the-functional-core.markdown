---
layout: post
title:  Strategies for extending the functional core - an effect EDSL 
date:   2022-01-27
categories: programming
---

## Intro

Pre-reqs:

* [Functional core, imperative shell architecture](https://github.com/kbilsted/Functional-core-imperative-shell/blob/master/README.md)
* Purity, referential transparency, side-effects
* EDSL means [embedded domain-specific language](https://en.wikipedia.org/wiki/Domain-specific_language#External_and_Embedded_Domain_Specific_Languages)
* [Abstract syntax-tree](https://en.wikipedia.org/wiki/Abstract_syntax_tree)

Rational:

* The functional core is more testable and more composable than the imperative shell or effectful code
* It's to be desired to extend the ratio of pure methods and functions in a code-base
* Sometimes you can easily extend the functional core by lifting out side-effects to calling code
* Sometimes, the side-effects are entangled inside business logic, and it's not clear if it's possible to "purify"

Consider the pipeline schema read-process-write, where "read" means reading IO, "process" means pure business logic, and "write" means writing to IO.

* Some times you have read-process in one function. Then it's often easy to lift out the read-part and pass it as an argument instead.
* Other times you have process-write in one function, in which case you can either mock the writing class in testing, or return a command object representing the write
* When read-process-write is entangled, it's sometimes possible to use an effect DSL to represent writes. Details below.

Consider the following use-case: A function to create x number of dummy users from a web request object, save them in database and show a result.

Coded in PHP below, but the pattern is language agnostic.

```php
function createDummyUsers(Request $request, St $st): array
{
    $times  = $request->getParam('times', 5);
    $dummyUsers = new SplStack();

    for (; $times > 0; $times--) {
        $user           = new User();
        $user->username = 'John Doe';

        // Using the EDSL builder to create an AST
        $st
            ->if(save($user))
            ->then(pushToStack($dummyUsers, $user->username));
    }

    return [
        'success'    => true,
        'dummyUsers' => $dummyUsers
    ];
}
```

And to use this code:

```php
$st = new St();
$result = createDummyUsers(new Request(), $st);
// You need an evaluator to run the AST from $st
(new Evaluator($st))->run();
renderJson($result);
```

The big benefit of wrapping side-effects in an AST that's evaluated, is that in your test code, you can use the same dry-run spy-evaluator in _all_ tests. One mock to rule them all.

```php
$user = new User();
// ... set properties
st(() -> user.save() ? void : throw new Exception("Could not save user"));
```

(An alternative is `st.throwOnFalse(fn () => user.save());`)

```php
st
  .if(() -> user.save())
  .then(() -> /* Do something */)
  .else(() -> /* Throw exception? */);
```

> But why?

To get rid of mocking and injection.  When writing the unit test, you can skip the `if` part and just run the `then` or `else` part to check the behaviour at success or failure. You don't even have to mock the state object `st`, just get the event queue and manipulate it however you want in the test code.

## Related concepts

* [Tagless-final](https://discuss.ocaml.org/t/explain-like-im-5-years-old-tagless-final-pattern/)
* [Flutent interface](https://martinfowler.com/bliki/FluentInterface.html)

## Thanks to

{:refdef: style="text-align: center;"}
<a href="https://www.limesurvey.org"><img src="{{ site.url }}/assets/img/limesurveylogo.png" alt="LimeSurvey" height="50px"/></a>
{: refdef}
{:refdef: style="text-align: center;"}
**Open-source survey tool**
{: refdef}

## Notes

* Another motivation is that mocking is often complex (and boring) to write, and reducing the need of mocking in testing will make your test suite simpler

The different categories of side-effects (or just "effects") are:

* Effects that can be delayed
* Effects that depend on each other
* Effects where the result is needed at once
