---
layout: post
title:  Strategies to make functions pure
date:   2023-04-11
categories: programming php
---

<style>
h4, h3 {
  display: none; /* hide */
}
h4 + p, h3 + p {
    padding: 10px;
    margin: 10px;
    color: #333;
}
h4 + p {
    background-color: rgb(221, 244, 255);
}
h3 + p {
    background-color: #fff8c4;
}
hr {
    margin: 2em;
    color: #fafafa;
    width: 70%;
    margin-left: auto;
    margin-right: auto;
}
</style>

#### Note
**&#x24D8;** This post uses PHP notation but the patterns are applicable to most OOP languages.

Pure functions are generally better than effectful functions. They can be:

* Combined more easily with other functions
* Tested without any mocking
* They have clear contract

In this article, there are two different side-effects[^7] we care about:

* Read
* Write[^1]

You can read/write to file, database, sockets, PHP session, etc.

A read can be _unconditional_, that is, it happens for all logical paths inside a function.

A write can be _independent_, which means that no logic in that function depends on the write.

Around reads and writes, you have chunks of pure logic, processing, or business logic, that can sometimes be moved to separate functions. In fact, the smaller your functions are on average, the higher the probability that you already have a good separation between effectful and pure code.

We are interested in which strategies can be applied to remove reads and writes from a function without causing considerable increase in complexity. Strategies that are idiomatic in the language you work with are to be preferred, of course.

---

**Unconditional read**

Consider the following function[^4]:

```php
function copySettings(int $id): void {
    $settings = getAllSettings($id);
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        writeCopyToDatabase($copy);
    }
}
```

As you can see, the first line happens for all logical paths, so it can be moved up one step in the stack trace, like this:

```php
function copySettings(array $settings): void {
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        writeCopyToDatabase($copy);
    }
}
```

Great! We've already made the function a bit easier to combine and test.[^2] For example, maybe someone would like to fetch the settings in some other way than by id?

By the way, if you have a function or method with many unconditional reads spread out in the function, it might be a hint to split it into multiple functions instead.

---

**Independent write**

Again looking at the `copySettings` function, we see that no logic after the write actually depends on it.[^3] There are a number of ways we can delay or defer the effect.

* Return a lambda wrapping the effect
* Return a command class, like `WriteSettingToDatabase` (an alternative to command classes is to use promises instead, though they can be harder to inspecct by the unit test code)
* Pass an `IO` class which accepts effects to be executed later, `$io->defer(new WriteSettingToDatabase($setting))`, or possibly `$io->defer('write.db', fn() => $db->save($setting))`.
* Use `yield`
* Use events

The example below wraps the writes in lambdas:

```php
function copySettings(array $settings): array {
    $writes = [];
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        $writes[] = fn() => $copy->save();
    }
    return $writes;
}
```

The drawback of returning lambdas is that you cannot inspect them further. If you have a function that returns a mix of lambdas doing different things, you probably want to know in your tests what exactly is happening. That's where command classes can be more useful.

```php
function copySettings(array $settings): array {
    $commands = [];
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        $commands[] = new WriteSettingToDatabase($copy);
    }
    return $commands;
}
```

Passing around an object that collects commands instead of returning them is useful if you don't want to pollute your functions' return types.

```php
function copySettings(array $settings, IO $io): void {
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        $io->defer(new WriteSettingToDatabase($copy));
    }
}

function caller() {
    $io = new IO();
    copySettings($id, $io);
    // ... possibly more code
    $io->runDeferred();
}
```

Combining command object with generators and yield:

```php
function copySettings(array $settings): Generator {
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        yield new WriteSettingToDatabase($copy);
    }
}

function caller()
{
    $writes = [];
    $writes = array_merge($writes, iterator_to_array(copySettings(getSettings($id))));
    // ... possibly more code
    array_map(fn($write) => $write->run(), $writes);
}
```

The handling code is pretty explicit in the `caller` above, but it can be improved by wrapping it in a class helper, I think.

In all the above cases it becomes the calling code's responsibility to make sure the commands are being run properly.

With events and command objects:

```php
function copySettings(array $settings, EventManager $em): void {
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        $em->fire('io.write', new WriteSettingToDatabase($copy));
    }
}

function caller(): void {
    $em = new EventManager();
    $em->subscribe('io.write', function ($command) { $command->run(); });
    copySettings(getSettings($id), $em);
}
```

The live event handler just runs the commands. In a unit-test setting, it would be a mock instead.

You can of course inject an event manager instead of having a hard-coded dependency like above, especially if you want to signal in the function signature that the function is effectful.

---

**Moving out logical chunks**

It's easy enough to mix effects with pure logic out of habit or stress. Here's an example which can be improved by moving out logic to separate methods. You don't have to care about the actual meaning of the code, just that no side-effects are happening after `getXmlFromTheme`.

```php
function getAttributesFromTheme(string $themeName): array
{
    $theme = $this->getTheme($themeName);
    if (empty($theme)) {
        return null;
    }
    $xml = $this->getXmlFromTheme($theme);
    if (empty($xml)) {
        return null;
    }
    $xmlAttributes = $xml->getNodeAsArray('attributes');
    if (!empty($xmlAttributes['attribute']['name'])) {
        $xmlAttributes['attribute'] = [($xmlAttributes['attribute']];
    }
    $attributes = [];
    foreach ($xmlAttributes['attribute'] as $attribute) {
        if (!empty($attribute['name'])) {
            $attributes[$attribute['name']] = array_merge(self::getBaseDefinition(), $attribute);
        }
    }
    return $attributes;
}
```

The logical chunk after the second `return` becomes its own function, and can now be tested without any mocking:

```php
function getAttributesFromTheme(string $themeName): array
{
    $theme = $this->getTheme($themeName);
    if (empty($theme)) {
        return null;
    }
    $xml = $this->getXmlFromTheme($theme);
    if (empty($xml)) {
        return null;
    }
    return $this->extractAttributes($xml);
}

```

---

**Conditional reads**

The simplest case of side-effect dependency is where a number of reads each depend on the previous one not returning null. So you get a pipeline like `read-read-read-doThing`, where a failed read would abort and return null.

That's easy enough with a simple `Pipe` class.

The example `getAttributesFromTheme` from above reads a theme domain entity from database, gets the belonging configuration file, and then extracts the attributes from the config file. This is a `read-read-process` pipeline.

Applying the pipe pattern, it could look like this[^8]:

```php
function getAttributesFromTheme(string $themeName): Pipe
{
    return Pipe::make(
        $this->getTheme(...),
        $this->getXmlFromTheme(...),
        $this->extractAttributes(...)
    )
    ->stopIfEmpty()
    ->from($themeName);
}

function caller(): void
{
    $attributes = $this->getAttributesFromTheme('mytheme')->run();
}
```

The pipe pattern can be used to deal with the `copySettings` example too, with a `forall` pipe method (`foreach` is already taken):

```php
function copySettings($settings): Pipe {
    return Pipe::make(
        $this->createCopy(...),
        $this->writeCopyToDatabase(...)
    )->forall($settings);
}
```

---

**Trailing writes**

If multiple early returns happen before a write, the function can possibly be split into a boolean pure function and the write itself.

```php
function setMySQLDefaultEngine(?string $dbEngine, Connection $db): void
{
    if (empty($dbEngine)) {
        return;
    }
    if (!in_array($db->driverName, [DB_TYPE_MYSQL]) {
        return;
    }
    $this->writeDefaultEngine($db);
}
```

Better is a split between the effect and the logic to decide to do the write:

```php
function shouldSetMySQLDefaultEngine(?string $dbEngine, Connection $db): bool
{
    if (empty($dbEngine)) {
        return false;
    }
    if (!in_array($db->driverName, [DB_TYPE_MYSQL])) {
        return false;
    }
    return true;
}

function caller(): void
{
    // ...
    if ($this->shouldSetMySQLDefaultEngine($dbEngine, $db)) {
        $this->writeDefaultEngine($db);
    }
    // ...
}
```

---

**Branching on a write**

The following example considers a more complex situation than just "stop on null-read in a read chain", where it branches on reads and writes when creating a new folder.

In pseudo-code:

```text
If folder exists
    Nothing to do
Else
    Create folder
    If create fails
        Abort with error
    Else
        Write file in folder
        If write fails
            Abort with error
```

PHP code:

```php
function createDirectory(string $uploadDir, int $id): bool
{
    $folder = $uploaddir . "/surveys/" . $id . "/files";
    $html   = "<html><head></head><body></body></html>";
    if (!file_exists($folder)) {
        if (!mkdir($folder, 0777, true)) {
            return false;
        } else {
            if (file_put_contents($folder . "/index.html", $html) === false) {
                return false;
            }
        }
    }
    return true;
}
```

This function can be made pure in two ways:

* Rewrite the logic to fit a pipe pattern
* Expression builder pattern (as explained shorty by Martin Fowler [here](https://www.martinfowler.com/dslCatalog/expressionBuilder.html))[^5]

A pipe-adapted version of the same code would look like this:

```php
function createDirectory(string $uploadDir, int $id): Pipe
{
    $folder = $uploaddir . "/surveys/" . $id . "/files";
    $html   = "<html><head></head><body></body></html>";
    return pipe(
        fn() => !file_exists($folder),
        fn() => mkdir($folder, 0777, true),
        fn() => file_put_contents($folder . "/index.html", $html)
    )->stopIfFalse();
}
```

There's a semantic problem here, since stopping if the file exists is different (should be different) than a failure to write (original code has same issue too).

Top read is unconditional, by the way (happens in all logical paths), so it can be moved out. We should also rewrite the lambdas to something that can be inspected. And probably throw on failure.

```php
function createDirectory(string $folder, bool $folderExists): Pipe
{
    $html = "<html><head></head><body></body></html>";
    if ($folderExists) {
        return pipe();
    } else {
        return pipe(
            new MakeDir($folder, 0777, true),
            new FilePutContents($folder . "/index.html", $html)
        )->throwIfFalse();
    }
}

function caller()
{
    // ...
    $folder = $this->bakeFolder($uploadDir, $id);
    try {
        $this->createDirectory($folder, file_exists($folder))->run();
    } catch (Exception $ex) {
        // ...
    }
}
```

Some people would recommend against boolean arguments like that.

Time to bring out the big guns. The next solution builds up an [expression tree](https://en.wikipedia.org/wiki/Abstract_syntax_tree) that can be evaluated independent of its construction. The performance hit is pretty obvious.

The functions `not`, `fileExists` etc all create _nodes_ in the tree, so they're not run until someone calls `$st->eval()` on the tree itself. Also, the logic of the nodes is _not_ inside the node objects themselves, but rather in the evaluator class (one mega-switch statement or such). This makes it possible to run any type of behaviour (in our case, mostly live "normal" behaviour vs mocked behaviour in the unit-tests) for the nodes[^6].

```php
use St\not;
use St\fileExists;
use St\makeDir;
use St\filePutContents;
function createDirectory(string $uploadDir, int $id, St $st): St
{
    // read-branch-write-write
    $folder = $uploaddir . "/surveys/" . $id . "/files";
    $html   = "<html><head></head><body></body></html>";
    return $st
        ->if(not(fileExists($folder)))
        ->then(
            $st
                ->if(makeDir($folder, 0777, true))
                ->then(filePutContents($folder . "/index.html", $html))
        );
}
```

An expression builder like this can be fully inspected by test code by passing a mock version of `St` - a big plus in my book.

In both these cases, we're separating the decision on what to do from the doing itself.

---

**Summary**

The following simple code improvement patterns were suggested:

* Move unconditional reads up in the stack trace
* Defer or delay independent writes
* Factor out pure business logic in smaller, independent functions

The following design patterns were described:

* Pipe
* Expression builder
* Command class

---

**Footnotes**

[^1]: We don't care about division-by-zero and exceptions as effects here.
[^2]: It would be interesting if a static analyzer could detect unconditional reads like this, but I've never seen one that can do it.
[^3]: Though it is missing defensive programming, to check and take action if the write fails.
[^4]: All code examples are fetched from [LimeSurvey](https://github.com/LimeSurvey/LimeSurvey/).
[^5]: In functional programming you can use the IO monad pattern to make effectful code pure, but it really only makes sense when the programming language supports monadic syntax.
[^6]: This pattern lies on the other end of the so called [expression problem](https://en.wikipedia.org/wiki/Expression_problem).
[^7]: I use "effect" and "side-effect" interchangeably.
[^8]: `foo(...)` in PHP is actual syntax to return a first-class callable for a function.
