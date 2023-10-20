---
layout: post
title:  100% test coverage, zero mocking
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

Forth link, read-process-write

All IO is put into invokable `Effect` classes.

Performance hit?

Can easily be made concurrent for any pipe.

FilePutContents vs FileGetContents, what's expected to be passed around in the pipe, and what's already decided or known when the pipe is created?

Link to pipeline design patter. Not the same as pipe operator.

Assumption:

* A computer program can be understood as a tree-structure of read-process-write pipelines [forth book]
* All side-effects are wrapped in small `Effect` classes
