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

What is a pipeline? Link to design pattern def.

Not the same thing as pipe operator: `|>`. The operator is type-safe but cannot be configured.

All IO is put into invokable `Effect` classes.
Also `Read`, `Write`, possibly `Rand` or event `Exception`.
Could be more precise, like `DatabaseRead` etc, if there's a use-case.

![Adapting]({{ site.url }}/assets/img/effectclass.png)

Performance hit?

Can easily be made concurrent for any pipe. `pipe()->fork(2)->foreach($collection)`.

FilePutContents vs FileGetContents, what's expected to be passed around in the pipe, and what's already decided or known when the pipe is created?

Link to pipeline design patter. Not the same as pipe operator.

Assumption:

* A computer program can be understood as a tree-structure of read-process-write pipelines [forth book]
* All side-effects are wrapped in small `Effect` classes

Cache read-effects?

Compare with https://github.com/amphp/pipeline

https://github.com/Crell/Transformer

Implicit glue, stack-based lang: https://www.youtube.com/watch?v=umSuLpjFUf8  "Concatenative programming and stack-based languages" by Douglas Creager 

https://peakd.com/php/@crell/don-t-use-mocking-libraries

https://fsharpforfunandprofit.com/pipeline/
