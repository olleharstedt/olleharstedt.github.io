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

### Warning
**&#x26a0;** DRAFT

#### Note
**&#x24D8;** This post uses PHP notation but the patterns are applicable to most languages.

Pure functions are generally better than effectful functions. They can be:

* Combined more easily with other functions
* Tested without any mocking
* They have clear contract

In this article, there are two different effects we care about:

* Read
* Write[^1]

You can read/write to file, database, sockets, PHP session, etc.

A read can be _unconditional_, that is, it happens for all logical paths inside a function.

A write can be _independent_, which means that no logic in that function depends on the write.

Around reads and writes, you have chunks of pure logic, processing, or business logic, that can sometimes be moved to separate functions.

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

---

**Independent write**

Again looking at the `copySettings` function, we see that no logic after the write actually depends on it.[^3] There are a couple of ways we can deley or defer the effect.

* Return a lambda wrapping the effect
* Return a command class, like `WriteSettingToDatabase`
* Pass an `IO` class which accepts effects to be executed later, `$io->defer(new WriteSettingToDatabase($setting))`
* Use `yield`

All of these alternatives have the drawback of giving more responsibility to the calling code.

```php
function copySettings(array $settings): array {
    $writes = [];
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        $writes = fn() => $copy->save();
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
        $commands = new WriteSettingToDatabase($copy);
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
```

And finally combining command object with generators and yield:

```php
function copySettings(array $settings, IO $io): generator {
    foreach ($settings as $setting) {
        $copy = createCopy($setting);
        yield new WriteSettingToDatabase($copy);
    }
}
```

In all the above cases it becomes the calling code's responsibility to make sure the commands are being run properly.

---

**Moving out logical chunks**

It's easy enough to mix effects with pure logic out of habit. Here's an example which can be improved by moving out logic to separate methods:

```php
possibly get permission
function getPermission()
{
    // construct the query conditions
    return $query->exec();
}
```

---

**Conditional reads and writes**

So far for the easy stuff, but what about writes and reads that depend on each other?

The simplest case of effect dependency is where a number of reads each depend on the previous one not returning null. So you get a pipeline like `read-read-read-doThing`, where a failed read would abort and return null.

That's easy enough with a simple `Pipe` class.

The example below reads a theme domain entity from database, gets the belonging configuration file, and then extracts the attributes from the config file. This is a `read-read-process` pipeline.

```php
function getAttributesFromTheme(string $themeName)
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

function caller()
{
    $attributes = $this->getAttributesFromTheme('mytheme');
}
```

Fixed with moving the unconditional read out, and applying the pipe pattern:

```php
function getAttributesFromTheme(string $themeName)
{
    return Pipe::make(
        $this->getTheme(...),
        $this->getXmlFromTheme(...),
        $this->extractAttributes(...)
    )
    ->stopIfEmpty()
    ->from($themeName);
}

function caller()
{
    $attributes = $this->getAttributesFromTheme('mytheme')->run();
}
```

The pipe pattern can be used to deal with `copySettings` too, with a `foreach` pipe feature:

```php
function copySettings($settings) {
    return Pipe::make(
        $this->createCopy(...),
        $this->writeCopyToDatabase(...)
    )->foreach($settings);
}
```

---

**Early returns and writes**

If multiple early returns happen before a write, the function can possibly be split into a boolean pure function and the write itself.

```php
function setMySQLDefaultEngine(?string $dbEngine, Connection $db)
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

function caller()
{
    // ...
    if ($this->shouldSetMySQLDefaultEngine($dbEngine, $db)) {
        $this->writeDefaultEngine($db);
    }
    // ...
}
```

---

**Branching on a read**

The following example branches on reads and writes when it creates a new folder.

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
* Expression builder pattern (as explained shorty by Martin Fowler [here](https://www.martinfowler.com/dslCatalog/expressionBuilder.html))

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

Top read is unconditional btw (happens in all logical paths), so it can be moved out:

```php
function createDirectory(string $folder, bool $folderExists): Pipe
{
    $html = "<html><head></head><body></body></html>";
    if ($folderExists) {
        return pipe();
    } else {
        return pipe(
            fn() => mkdir($folder, 0777, true),
            fn() => file_put_contents($folder . "/index.html", $html)
        )->stopIfFalse();
    }
}

function caller()
{
    // ...
    $folder = $this->bakeFolder($uploadDir, $id);
    $this->createDirectory($folder, file_exists($folder))->run();
    // ...
}
```

Some people would recommend against boolean arguments like that.

To get a proper exception on failure one could use `$pipe->throwOnFalse()` instead of just stopping the pipe.

Time to bring out the big guns. The next solution builds up an expression tree that can be evaluated independent of its construction. The performance hit is pretty obvious.

The functions `not`, `fileExists` etc all create _nodes_ in the tree, so they're not run until someone calls `$st->eval()` on the tree itself.

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

Problematic for the imperative shell to deal with lots of different returned objects from the core? Can maybe be solved if Pipe and St both implement same interface.

I'm not covering the monadic way here, but it could be done with promises etc.

---

**Summary**

Following code improvement patterns:

* Move unconditional reads up in the stack trace
* Defer or delay independent writes

The following design patterns were considered in this blog post:

* Pipe
* Expression builder
* Command class

Subscriber-observer pattern to be used for defer?

When write depends on result from read, generate an AST (tagless-final)

Capabilities.

Psalm and enforcing interfaces.

Easier to allow for mocking? When is purity better than mocks?

Diminishing return.

Mocking works equally well, but purity gives better composability...?

State monad? But can't inspect which effect it is? Similar problem with pipe?

`read . () => doThingWithResult()`

Example: Copy domain entity, command class. `$io->defer('db.save', () => $thing->save());`

```php
class CopyThingCommand implements CommandInterface {
    public function run(array $options) {
        // Depending on $options, copy translations, settings, etc
    }
    public function copyTranslations();
    public function copySettings();
}
```

And

```php
public function copySettings($id) {
    // Get all settings belonging to $id from database
    // Loop them
    // Create copy
    // Write copy to database
}
```

First step, move the read out from the method, since it's always happening.

Second step, we can defer the all writes.

Fluid interface.

Negative example, copy a folder. React if write failes etc. Unlink. While readdir, recursively.

Separate the decision about the effect from the effect itself. But when read depends on write depends on read...

Is there anything to gain from lifting out writes?
Contract gets more complicated: pure > read from immutable state > read from state > io read > io write.

```php
foreach ($surveys as $survey) {
    if ($survey->hasTokensTable) {
        $token = \Token::model($survey->sid)->findByAttributes(['participant_id' => $participant->participant_id], "emailstatus <> 'OptOut'");
        if (!empty($token)) {
            $token->emailstatus = 'OptOut';
            defer(() => $token->save());
            $optedoutSurveyIds[] = $survey->sid;
        }
    }
}
```

```php
foreach ($surveys as $survey) {
    pipe(
        $survey->hasTokensTable(...),
        fn() => getToken($survey, $participant),
        fn($token) => $token->emailstatus = 'OptOut' && $token->save() && $optedoutSurveyIds[] $survey->sid
    );
}
```

```
return new Pipe(
    $survey->hasTokensTable(...),
    $this->fetchToken(...),
    $this->saveToken(...)
);
```

```php
pipe(
    fn() => $this->getToken($survey, $participant),
    fn($token) => 
);
```

```php
foreach ($surveys as $survey) {
    if survey has tokens table
    and token
    then
        set email status
        defer save
        collect result
}
```

Would `defer` make it harder to understand the function? Because now the caller must run the deferred statements.

Unconditional reads and writes that nothing depends on are less complex than reads and writes that enforce a certain order of operation.

cp vs mv
read-write, read-write-write (second write deletes file)

defer/start or defer/end

Can use db transaction or not, if you wish; also defer some decisions.

---

TMP

Most pipe libs seem to use classes to bind together, but I think that's foregoing its most useful use-case, namely to string together and separate pure methods from effectful ones (methods that read/write to database, file, socket, etc).

Function that writes at the end unless early return. See SurveyActivator class.

TODO: Important difference to pipeline libs is:

1. Stop the pipeline on failure
2. Primarily pipe methods together, not classes

How to get proper error message from a pipe failure?

TODO: Find independent write example that does not depend on read before it

TODO: Note, most examples are taken from the LimeSurvey code-base.

TODO: Add section about moving out logical chunk.

---

**Footnotes**

[^1]: We don't care about division-by-zero and exceptions as effects here.
[^2]: It would be interesting if a static analyzer could detect unconditional reads like this, but I've never seen one that can do it.
[^3]: Though it is missing defensive programming, to check and take action if the write fails.
[^4]: All code examples are fetched from [LimeSurvey](https://github.com/LimeSurvey/LimeSurvey/).
