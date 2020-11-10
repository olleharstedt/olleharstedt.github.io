---
layout: post
title:  Pipeline-oriented programming with concurrency
date:   2020-08-03
categories: php
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/pipe.webp" alt="Pipes" height="400px"/>
{: refdef}
{:refdef: style="text-align: center;"}
_One mock to rule them all_
{: refdef}

## Introduction

Read-process-write pipeline, where each triple is a _stage_. Enforced on framework level; don't inject any object with side-effects. The side-effect factory produces promises that can be resolved concurrently when wanted.

Yield as async/await. Add new IO and return to logic. Yield always waits. Yield array of promises is concurrent.

Race-condition if IO 1 takes longer than IO 2 and IO 2 depends on 1 to be finished.

Redux-saga. Amphp.

No mocking needed. Only mock the _result_ of the resolved side-effects.

A pipeline is an array of:

* Commands objects (promises)
* Filters
* Callables (processing and push write commands using yield)

Even in Haskell there's no framework that makes sure your business logic isn't polluted with side-effects.

```php
public function revertAdmin(int $userId = 1, IO $io): array
{
    return [
        $io->db->queryOne('SELECT * FROM users WHERE id = :id', [':id' => $userId]),
        new FilterEmpty($io->stdout->printline('Found no such user')),
        function (array $user) use ($io) {
            yield $io->stdout->printline('Yay, found user!');
            $becomeAdmin = $user['is_admin'] ? 0 : 1;
            $affectedRows = yield $io->db->query(
                sprintf(
                    'UPDATE users SET is_admin = %d WHERE id = %d',
                    $becomeAdmin,
                    $user['id']
                )
            );
            if ($becomeAdmin === 1) {
                yield $io->stdout->printline('User is now admin');
            } else {
                yield $io->stdout->printline('User is no longer admin');
            }
        },
    ];
}
```

## MVC

The model-view-controller pattern is based on the faulty assumption that's there a difference reading from a browser than it is reading from a database. The design patter is cutting along the wrong lines.
