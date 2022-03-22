---
layout: post
title:  One universal dry-run mock-spy AST evaluator to rule them all
date:   2022-03-22
categories: programming
---

This blog post outlines a strategy to remove the need for mocking in tests, by using an expression builder class for side-effects.

Examples are made in PHP, but the concept is applicable to any OOP language.

Motivating example (from [here](https://blog.ploeh.dk/2016/09/26/decoupling-decisions-from-effects/)):

```java
public static string GetUpperText(string path)
{
    if (!File.Exists(path)) return "DEFAULT";
    var text = File.ReadAllText(path);
    return text.ToUpperInvariant();
}
```

In PHP with the effect EDSL:

```php
function getUpperText(string $file, St $st)
{
    $result = 'DEFAULT';
    $st
        ->if(fileExists($file))
        ->then(set($result, fileGetContents($file)))
        ();
    return strtoupper($result);
}
```

In PHP with a mockable class:

```php
function getUpperText(string $file, IO $io)
{
    $result = 'DEFAULT';
    if ($io->fileExists($file)) {
        $result = $io->fileGetContents($file);
    }
    return strtoupper($result);
}
```

The St class will build an abstract-syntax tree, which is then evaluated when invoked. It can be injected with either a live evaluator, or a dry-run evaluator which works as both mock, stub and spy.

St can also be used to delay or defer effects - just omit the invoke until later.

The unit test looks like this:

```php
// Instead of mocking return types, set the return values
$returnValues = [
    true,
    'Some example file content, bla bla bla'
];
$ev = new DryRunEvaluator($returnValues);
$st = new St($ev);
$text = getUpperText('moo.txt', $st);
// Output: string(38) "SOME EXAMPLE FILE CONTENT, BLA BLA BLA"
var_dump($text);
// Instead of a spy, you can inspect the dry-run log
var_dump($ev->log);
/* Output:
   array(5) {
   [0] =>
   string(13) "Evaluating if"
   [1] =>
   string(27) "File exists: arg1 = moo.txt"
   [2] =>
   string(15) "Evaluating then"
   [3] =>
   string(33) "File get contents: arg1 = moo.txt"
   [4] =>
   string(50) "Set var to: Some example file content, bla bla bla"
   }
 */
```

The St class scales differently than mocking, so it's not always sensible to use.

Full code in [this gist](https://gist.github.com/olleharstedt/e18004ad82e57e18047690596781a05a).
