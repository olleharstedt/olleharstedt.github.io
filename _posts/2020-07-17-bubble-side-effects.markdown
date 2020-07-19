---
layout: post
title:  Put all your side-effects in command objects, why not?
date:   2020-07-17
categories: php
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/zoiberg.jpg" alt="Why not?" height="400px"/>
{: refdef}
{:refdef: style="text-align: center;"}
_Zoidberg approves_
{: refdef}

## Introduction

Side-effects generally make writing automatic tests a little bit trickier. TODO.

Proof-of-concept for a design pattern that might or might not be a good idea.

Pros:

* Pure methods, very easy to write unit-tests for

Cons:

* Non-idiomatic
* Hard to compose methods that return side-effects

## Implementation

A cronjob that installs your favourite web application.

What's different from a "normal" code-base?

* A class `SideEffectFactory` that makes side-effect command objects
* A class `SideEffectRunner` that runs those objects

```php
class AppInstaller
{
    /** @var SideEffectFactory */
    private $sef;

    /**
     * @param SideEffectFactory $sef
     */
    public function __construct(SideEffectFactory $sef)
    {
        $this->sef = $sef;
    }

    /**
     * @param InstallationData $data
     * @return IOAction[]
     */
    public function install(InstallationData $data)
    {
        /** @var IOAction[] */
        $sideEffects = [];

        if ($this->folderDoesNotExist()) {
            $sideEffects[] = $this->sef->makeFileIOAction(
                'unzip app.zip '  . $data->targetFolder,
                // Second argument for rollback.
                'rm -r '  . $data->targetFolder
            );
        }

        if ($this->databaseDoesNotExist()) {
            $sideEffects[] = $this->sef->makeDatabaseIOAction(
                'CREATE DATABASE ' . $data->databaseName,
                'DROP DATABASE ' . $data->databaseName
            );
        }

        $sideEffects[] = $this->sef->makeNginxIOAction(
            'add domain ' . $data->domain,
            'remove domain ' . $data->domain
        );

        return $sideEffects;
    }
}

class SideEffectRunner
{
    private $fileIO;
    private $databaseIO;
    private $nginxIO;

    // TODO: SideEffectRunner needs ALL IO systems injected?
    public function __construct($fileIO, $databaseIO, $nginxIO)
    {
        $this->fileIO = $fileIO;
        $this->databaseIO = $databaseIO;
        $this->nginxIO = $nginxIO;
    }

    /**
     * @param IOAction[] $actions
     * @return array{$success: bool, $message: string}
     */
    public function run(array $actions)
    {
        $done = [];
        try {
            foreach ($action => $action) {
                $this->runAction($action);
                $done[] = $action;
            }
        } catch (Exception $ex) {
            $this->rollback($done);
            return [false, $ex->getMessage()];
        }

        return [true, null];
    }

    /**
     * @param IOAction[] $actions
     * @return void
     */
    private function rollback(array $actions)
    {
        // NB: Don't catch exceptions here, because it should be fatal failure.
        foreach ($actions as $action) {
            $this->rollbackAction($action);
        }
    }

    /**
     * @param IOAction $action
     * @return void
     */
    private function runAction(IOAction $action)
    {
        if ($action instanceof FileIOAction) {
            $action->run($this->fileIO);
        } elseif ($action instanceof DatabaseIOAction) {
            $action->run($this->databaseIO);
        } elseif ($action instanceof NginxIOAction) {
            $action->run($this->nginxIO);
        }
    }

    /**
     * @param IOAction $action
     * @return void
     */
    private function rollbackAction(IOAction $action)
    {
        if ($action instanceof FileIOAction) {
            $action->rollback($this->fileIO);
        } elseif ($action instanceof DatabaseIOAction) {
            $action->rollback($this->databaseIO);
        } elseif ($action instanceof NginxIOAction) {
            $action->rollback($this->nginxIO);
        }
    }
}

class InstallController
{
    public function actionInstall(int $installationId)
    {
        $connection = new DatabaseConnection();
        $fetcher = new InstallationDataFetcher($connection);
        $data = $fetcher->fetch($installationId);
        $appInstaller = new AppInstaller(
            new SideEffectFactory()
        );
        $effects = $appInstaller->install($data);
        // TODO: Need all IO classes?
        $runner = new SideEffectRunner(
            new FileIO(),
            $connection,
            new NginxIO()
        );
        try {
            list($success, $message) = $runner->run($effects);
            if ($success) {
                echo 'All good';
            } else {
                echo 'Failed and rolled back: ' . $message;
            }
        } catch (Exception $ex) {
            echo 'Rollback failed, please repair manually: ' . $ex->getMessage();
        }
    }
}
```
