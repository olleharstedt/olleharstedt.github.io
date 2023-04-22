---
layout: post
title:  Strategies to make functions pure
date:   2023-04-11
categories: programming php
---

DRAFT

<style>
h4 {
  display: none; /* hide */
}
h4 + p {
    padding: 10px;
    background-color: rgb(221, 244, 255);
    margin: 10px;
    color: #333;
}
</style>

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

We are interested in which strategies can be applied to remove reads and writes from a function without causing considerable increase in complexity. Strategies that are idiomatic in the language you work with are to be preferred, of course.

**Unconditional read**

Consider the following function:

```php
function copySettings($id) {
    // Get all settings belonging to $id from database
    // Loop them
    //   Create copy
    //   Write copy to database
}
```

As you can see, the first line happens for all logical paths, so it can be moved up one step in the stack trace, like this:

```php
function copySettings($settings) {
    // Loop them
    //   Create copy
    //   Write copy to database
}
```

Great! We've already made the function a bit easier to combine and test.[^2]

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

In all the above cases it's the calling code's responsibility to make sure the commands are being run properly.

**Conditional reads and writes**

So far for the easy stuff, but what about writes and reads that depend on each other?

The simplest case of effect dependency is where a number of reads each depend on the previous one not returning null. So you get a pipeline like `read-read-read-doThing`, where a failed read would abort and return null.

That's easy enough with a simple `Pipe` class.

The example below reads a theme domain entity from database, gets the belonging configuration file, and then extracts the attributes from the config file. This is a `read-read-process` pipeline.

```php
function getAttributesFromTheme(string $themeName)
{
    $theme = getTheme($themeName);
    if (empty($theme)) {
        return null;
    }
    $xml = getXmlFile($theme->path);
    if (empty($xml)) {
        return null;
    }
    return extractAttributes($xml);
}

function caller()
{
    $attributes = getAttributesFromTheme('mytheme');
}
```

Fixed with moving the unconditional read out, and applying the pipe pattern:

```php
function getAttributesFromTheme()
{
    return Pipe::make(
        $this->getXmlFile(...),
        $this->extractAttributes(...)
    )->stopIfEmpty();
}

function caller()
{
    $attributes = getAttributesFromTheme()->run(getTheme('mytheme'));
}
```

Depend on logic vs depend on reads/writes.

Defer + exception = :( Running $refer class in shutdown function not that fun, especially since you don't know what went wrong.

Defer with lambda, can't check what's happening in test. Effect class - check its name.

Subscriber-observer pattern to be used for defer?

When write depends on result from read, generate an AST (tagless-final)

AR vs repository pattern?

Capabilities.

Psalm and enforcing interfaces.

Easier to allow for mocking? When is purity better than mocks?

Diminishing return.

Mocking works equally well, but purity gives better composability...?

State monad? But can't inspect which effect it is?
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

**Footnotes**

[^1]: We don't care about division-by-zero and exceptions as effects here.
[^2]: It would be interesting if static analyzer could detect unconditional reads like this, but I've never seen one that can do it.
[^3]: Though it is missing defensive programming, to check and take action if the write fails.
