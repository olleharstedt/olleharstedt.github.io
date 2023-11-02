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

**The why**

Writing mocks and stubs and spys is a total PITA, and I'm looking for new ways to avoid it. This is one possible concept, with a couple of other benefits (and some drawbacks).

(Obviously I do not recommend 100% test coverage, this is just to prove a point. Your test coverage should be defined by your risk analysis.)

**Intro**

A pipeline[^1] is a certain design pattern to deal with processes where each output becomes the input for the next process step, like:

    input -> f -> g -> h -> output

Many, many things are implicit pipelines in web development, so you'd think it'd be a more established pattern.

The middleware pattern[^2] is a pipeline of a sort, but its "big" design limits its applicability, especially when it comes to eradicating mock code in tests.

Also note that the pipeline design pattern is not the same thing as the pipe _operator_: `|>`[^3]. The pipe operator is type-safe but cannot be configured the same way a pipe object can, as I will demonstrate below.

**A pipeline class**

<p align=center>
<img src="/assets/img/pipeline.png"/>
</p>

All IO is put into invokable `Effect` classes.
Also `Read`, `Write`, possibly `Rand` or even `Exception`.
A database query would implement the read or write interface depending on if it's a select or update/insert.
Plugin events could be effects too, opening up for customization (and spaghetti code...).

![Adapting]({{ site.url }}/assets/img/effectclass.png)

**Example use-case**

As an example, fetch data from a URL and process it:

    URL -> fetch -> html2markdown -> first_paragraph

Or in PHP;

```php
$result = pipe(             // pipe() is a helper function that returns a pipeline object
    new FileGetContents(),  // file_get_contents is wrapped in an invokable class
    htmlToMarkdown(...),    // Using the League\HTMLToMarkdown library
    firstText(...)          // Just a substring call
)
    ->from($url);           // ->from() defines the start value of the pipe
    ->run();                // Runs the pipeline
```

To test this piece of code, we need to **mock out** `FileGetContents` to return different test values instead. But, since **replacing IO effects** is supported by the pipeline class already, it's enough for us to do:

```php
$result = pipe(
    new FileGetContents(),
    htmlToMarkdown(...),
    firstText(...)
)
    // The magic part: all side-effects can be lifted out with a simple method call.
    ->replaceEffect('Query\Effects\FileGetContents', $dummyContent)
    ->from($dummyUrl)
    ->run();
```

Since functions will return pipelines without running them, the effects are **deferred** until the calling code runs it.

```php
function getUrl(string $url): Pipeline  // Only thing missing here is the generic notation Pipeline<string[]>
{
    return pipe(
        new FileGetContents(),
        htmlToMarkdown(...),
        firstText(...)
    )->from($url);
}
```

**Other benefits**

Some natural benefits occur when structuring your code as pipelines:

1. You can easily **cache** side-effects using a `Cache` effect class
2. You can easily **fork** when your input is a bigger array

The following code forks into two processes[^4] and also caches the result from `FileGetContents`:

```php
$result = pipe(
    // The file read effect is wrapped in a cache effect
    new Cache(new FileGetContents()),
    htmlToMarkdown(...),
    firstText(...)
)
    // At-your-fingertips parallelism
    ->fork(2)
    // The Cache effect class uses the injected cache
    ->setCache($cache)
    // Using map() here; foreach() or fold() are other possible iterations
    ->map($urls);
```

The same technique can be used to replace the cache effect as above, using `replaceEffect`.

The pipeline can **recursively** run pipelines returned by any of the processing steps. In this way, a computer program is structured like a tree of `read-process-write` pipelines[^5], and nothing gets executed until the top layer calls `run`; you separate - at least to a higher degree - what to do from how to do it[^6].

**Drawbacks**

There are some drawbacks with this approach of course.

* Performance might take a hit if you replace normal function calls with invokable classes. A compiled language might deal with this better than PHP.
* Type-safety is recuced. Instead of compile time errors for passing the wrong argument around, the pipeline will throw runtime exceptions.
* The lack of generic notation in PHP obfuscates the return values of functions. `string` becomes `Pipeline` as a return type, but what we want is `Pipeline<string>`.
* Implicit glue also means the pipeline payload is implicit, which can obfuscate the code a bit. Compare with Forth and its implicit stacks.

**Going forward**

Native language support for pipelines and effects?

**Other resources**

Compare with https://github.com/amphp/pipeline

https://github.com/Crell/Transformer

Implicit glue, stack-based lang: https://www.youtube.com/watch?v=umSuLpjFUf8  "Concatenative programming and stack-based languages" by Douglas Creager 

https://peakd.com/php/@crell/don-t-use-mocking-libraries

https://fsharpforfunandprofit.com/pipeline/

https://www.youtube.com/watch?v=_IgqJr8jG8M - Stanford Seminar - Concatenative Programming: From Ivory to Metal

Link to query repo.

**Footnotes**

[^1]: [Design Patterns: Implementing Pipeline design pattern](https://levelup.gitconnected.com/design-patterns-implementing-pipeline-design-pattern-824bd2d42bab)
[^2]: [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15)
[^3]: [PHP RFC: Pipe Operator v2](https://wiki.php.net/rfc/pipe-operator-v2) or [Pipelining with the `|>` operator in OCaml](https://blog.shaynefletcher.org/2013/12/pipelining-with-operator-in-ocaml.html)
[^4]: Using [spatie/fork](https://github.com/spatie/fork)
[^5]: Thinking Forth?
[^6]: Command query separation?
