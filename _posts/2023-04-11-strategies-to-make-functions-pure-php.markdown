---
layout: post
title:  Strategies to make functions pure in PHP
date:   2023-04-11
categories: programming php
---

DRAFT

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
class CopyThingCommand {
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
