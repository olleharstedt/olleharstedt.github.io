---
layout: post
title:  100% test coverage, zero mocking 
subtitle: A study in imperative shell, functional core using the pipeline design pattern
date:   2023-10-19
categories: programming php
---

<style>
h4, h3 {
  display: none; /* hide */
}
h4 + p {
    padding: 10px;
    background-color: rgb(221, 244, 255);
    margin: 10px;
    color: #333;
}
h3 + p {
    padding: 10px;
    background-color: #fff8c4;
    margin: 10px;
    color: #333;
}
</style>

### Warning
**&#x26a0;** DRAFT

**The why**

Writing mocks and stubs and spys is a total PITA, and I'm looking for new ways to avoid it. This is one possible concept, with a couple of other benefits (and some drawbacks).

**Intro**

A pipeline[^1] is a certain design pattern to deal with processes where each output becomes the input for the next process step, like:

    input -> f -> g -> h -> output

Many, many things are implicit pipelines in web development, so you'd think it'd be a more established pattern.

The middleware pattern[^2] is a pipeline of a sort, but its "big" design limits its applicability, especially when it comes to eradicating mock code in tests.

Also note that the pipeline design pattern is not the same thing as the pipe _operator_: `|>`[^3]. The operator is type-safe but cannot be configured the same way as a pipe object can.

**A pipeline class**

<p align=center>
<img src="/assets/img/pipeline.png"/>
</p>

All IO is put into invokable `Effect` classes.
Also `Read`, `Write`, possibly `Rand` or event `Exception`.
Could be more precise, like `DatabaseRead` etc, if there's a use-case.

![Adapting]({{ site.url }}/assets/img/effectclass.png)

**Example use-case**

As an example, fetch data from an array of URLs and process them:

    URLs -> fetch -> html2markdown -> first_paragraph

Or in PHP;

```php
$result = pipe(             // pipe() is a helper function that returns a pipeline object
    new FileGetContents(),  // file_get_contents is wrapped in an invokable class
    htmlToMarkdown(...),    // Using the League\HTMLToMarkdown library
    firstText(...)          // Just a substring call
)
    ->from($urls);          // ->from() defines the start value of the pipe
    ->run();                // Runs the pipeline
```

To test this piece of code, we need to **mock out** `FileGetContents` to return different test values instead. But, since **replacing IO effects** is supported by the pipeline class already, it's enough for us to do:

```php
$result = pipe(
    new FileGetContents(),
    htmlToMarkdown(...),
    firstText(...)
)
    ->replaceEffect('Query\Effects\FileGetContents', $dummyContent)
    ->from($dummyUrl)
    ->run();
```

Since functions will return pipelines without running them, the effects are **deferred** until the calling code runs it.

```php
function getUrls(array $urls): Pipeline  // Only thing missing here is the generic notation Pipeline<string[]>
{
    return pipe(
        new FileGetContents(),
        htmlToMarkdown(...),
        firstText(...)
    )->from($urls);
}
```

**Other benefits**

Some natural benefits occur when structuring your code as pipelines:

1. You can easily cache side-effects using a `Cache` effect class
2. You can easily `fork` when your input is a bigger array

The following code forks into two processes[^4] and also caches the result from `FileGetContents`:

```php
$result = pipe(
    new Cache(new FileGetContents()),
    htmlToMarkdown(...),
    firstText(...)
)
    ->fork(2)
    ->setCache($cache)
    ->map($urls);
```

The same technique can be used to replace the cache effect as above.

**Drawbacks**

There are some drawbacks with this approach of course.

* Performance might take a hit if you replace normal function calls with invokable classes. A compiled language might deal with this better than PHP.
* Type-safety diminishes. Instead of compile time errors for passing the wrong argument around, the pipeline will throw runtime exceptions.
* Implicit glue also means the pipeline payload is implicit, which can obfuscate the code a bit. Compare with Forth and its implicit stacks.

Assumption:

* A computer program can be understood as a tree-structure of read-process-write pipelines [forth book]
* All side-effects are wrapped in small `Effect` classes

Do not advice 100% test coverage but rather risk-driven coverage - most tests for those parts which failure has the highest impact * probability.

Compare with https://github.com/amphp/pipeline

https://github.com/Crell/Transformer

Implicit glue, stack-based lang: https://www.youtube.com/watch?v=umSuLpjFUf8  "Concatenative programming and stack-based languages" by Douglas Creager 

https://peakd.com/php/@crell/don-t-use-mocking-libraries

https://fsharpforfunandprofit.com/pipeline/

https://www.youtube.com/watch?v=_IgqJr8jG8M - Stanford Seminar - Concatenative Programming: From Ivory to Metal

Performance hit?

**Footnotes**

[^1]: [Design Patterns: Implementing Pipeline design pattern](https://levelup.gitconnected.com/design-patterns-implementing-pipeline-design-pattern-824bd2d42bab)
[^2]: [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15)
[^3]: [PHP RFC: Pipe Operator v2](https://wiki.php.net/rfc/pipe-operator-v2) or [Pipelining with the `|>` operator in OCaml](https://blog.shaynefletcher.org/2013/12/pipelining-with-operator-in-ocaml.html)
[^4]: Using [spatie/fork](https://github.com/spatie/fork)
