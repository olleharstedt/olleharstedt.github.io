---
layout: post
title:  Evaluating a Type 3 code clone detection algorithm for PHP
date:   2021-06-17
categories: programming
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/clone2.jpg" alt="Clone" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
*Clones - good or bad?*
{: refdef}

## Introduction

While reading some of our legacy code I got annoyed by code duplication and lack of function composition. Since I've been working on our CI pipeline a lot this year, I wanted to figure out if it was possible to ban clones from ever entering the master branch, by flagging a commit as red if it introduced a new clone.

## Definitions

<div style='margin: 1em 3em;'>
<table>
<tr>
<td><span class='fa fa-icon fa-info-circle fa-2x'></span></td>
<td>This section is copied from the <a href="https://manual.limesurvey.org/Code_quality_guide#Code_duplication">LimeSurvey code quality guide</a>.</td>
</tr>
</table>
</div>

There are four main types of code clones defined by researchers:

* Type 1 (exact, except for whitespace and comments)
* Type 2 (exact, except for renaming of identifiers)
* Type 3 (might include removed or added lines)
* Type 4 (different but semantically same)<ref>Liuqing Li et al, CCLearner: A Deep Learning-Based CloneDetection Approach</ref><ref>Andrew Walker et al, Open-Source Tools and Benchmarks for Code-CloneDetection: Past, Present, and Future Trends</ref>

The **edit distance** can be seen as the numbers of changes needed between two clones to make them identical. These changes are in terms of tokens, not white-space or variable naming.

A **false positive** is a clone detected by an algorithm, which is in fact not a clone. **False negative** is the opposite, where a clone is present in the code-base, but the algorithm is not able to detect it. Both should be decreased as much as possible.

Some reports paint a picture that code clones are not harmful for changeability, or can not be proven to be harmful (see "Do Code Clones Matter?").

## Tools

Many (all?) big code analysis tools also include code duplication detection, like Scrutinizer or SonarQube. There are smaller tools, too, like:

* [phpcpd](https://github.com/sebastianbergmann/phpcpd)
* [jscpd](https://github.com/kucherenko/jscpd)

I didn't manage to find out which algorithms are used by Scrutinizer and SonarQube, but phpcpd and jscpd both uses the [Rabin-Karp algorithm](https://en.wikipedia.org/wiki/Rabin%E2%80%93Karp_algorithm). It's a fast algorithm, but it can only detect Type 1 and Type 2 clones. I had a hard time navigating code duplication only with the bigger tools.

## Examples

Let's take an example that bothered me especially (disregarding other code quality issues here):

```php
if (\PHP_VERSION_ID < 80000) {
    $bOldEntityLoaderState = libxml_disable_entity_loader(true);
}
$sQuestionConfigFilePath = App()->getConfig('rootdir') . DIRECTORY_SEPARATOR . $pathToXML . DIRECTORY_SEPARATOR . 'config.xml';
if (!file_exists($sQuestionConfigFilePath)) {
    throw new Exception(gT('Extension configuration file is not valid or missing.'));
}
$sQuestionConfigFile = file_get_contents($sQuestionConfigFilePath);
```

This snippet had a duplicate in which the middle line was _slightly_ different:

```php
$sQuestionConfigFilePath = App()->getConfig('rootdir') . DIRECTORY_SEPARATOR . $sConfigPath;
```

The lines before and the lines after the changed line are both too small to be reported as Type 2 clones. What you need is an algorithm to deal with small changes.

## ConQAT

[ConQAT](https://www.cqse.eu/en/news/blog/conqat-end-of-life/) was an open-source tool to analyze different aspects of a code-base, including duplication. It's now end-of-life. They wrote a brilliant paper, [Do Code Clones Matter?](https://www.cqse.eu/fileadmin/content/news/publications/2009-do-code-clones-matter.pdf) (PDF) that outlines the basics of the algorithm including an evaluation project. Like Rabin-Karp, it's token based, but uses a [suffix tree](https://en.wikipedia.org/wiki/Suffix_tree) for comparison instead of hash, which makes it possible to configure an edit distance when comparing; you compare by similarity instead of equality.

The algorithm was released as open-source under the Apache 2.0 license, coded in Java.

## Porting

We don't use Java in-house, only PHP, and so I wanted a good CLI tool in the Unix philosophy - do one thing. Since neither phpcpd nor jscpd could detect Type 3 clones, I decided to port the algorithm to PHP, approximately 2k LoC. Most of it is already done. The PR is being worked on [here](https://github.com/sebastianbergmann/phpcpd/pull/199), you can clone it (no pun intended) and run it locally if you wish.

## Evaluation

In the end, was it worth it? Does our code-base contain enough Type 3 clones to motivate the slower run time of the new algorithm compared to the much faster hash algorithm? And do those clones contain faults? Don't know. So far, it seems like the example above, where the middle line was changed, is not too common. Often a Type 3 clone can be identified by two smaller Type 2 clones after each other. The new algorithm is more precise, though, and more often detect the _full_ clone without stopping at a small token change.

I'm struggling still on how to integrate it in our CI. I think a first step would be _single-file analysis_, where you run the algorithm on each file separately. This way you can tune the algorithm to be more sensitive, assuming single classes can be better factored than an entire project, especially with legacy code.

Thanks for reading! Ping me on Github if you have any questions. :)

Olle

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/limesurveylogo.png" alt="LimeSurvey" height="50px"/>
{: refdef}
{:refdef: style="text-align: center;"}
**Open-source survey tool**
{: refdef}
