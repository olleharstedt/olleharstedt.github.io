---
layout: post
title:  Strategies to make functions pure in PHP
date:   2023-04-11
categories: programming php
---

DRAFT

Pure functions are generally better than effectful functions.

* Can be combined more easily
* Can be tested without any mocking

Two different effects we care about:

* Read
* Write

You can read/write to file, database, curl, PHP session, etc.

A read can be _unconditional_, that is, it happens for all logical paths inside a function.

A write can be _independent_, which means that no read in that function depends on the write.

Move read from before

Defer write with yield

Defer write with command class. `__destructor` from SO.

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
