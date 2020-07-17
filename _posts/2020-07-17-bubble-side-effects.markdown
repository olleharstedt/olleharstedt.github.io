---
layout: post
title:  Bubble up you side-effects to the top
date:   2020-07-17
categories: php
---

```php
class AppInstaller
{
    /** @var SideEffectFactory */
    private $sef;
    /** @var InstalltionData */
    private $data;

    /**
     * @param SideEffectFactory $sef
     * @param InstalltionData $data
     */
    public function __construct(SideEffectFactory $sef, InstalltionData $data)
    {
        $this->sef = $sef;
        $this->data = $data;
    }

    /**
     * @return IOAction[]
     */
    public function install()
    {
        /** @var IOAction[] */
        $sideEffects = [];

        if ($this->folderDoesNotExist()) {
            $fileAction = $this->sef->makeFileIOAction();
            $fileAction->command = 'unzip app.zip '  . $data->targetFolder;
            $fileAction->rollback = 'rm -r '  . $data->targetFolder;
            $sideEffects[] = $fileAction;
        }

        if ($this->databaseDoesNotExist()) {
            $dbAction = $this->sef->makeDatabaseIOAction();
            $dbAction->command = 'CREATE DATABASE ' . $data->databaseName;
            $dbAction->rollback = 'DROP DATABASE ' . $data->databaseName;
            $sideEffects[] = $dbAction;
        }

        $nginxAction = $this->sef->makeNginxIOAction();
        $nginxAction->command = 'add domain ' . $data->domain;
        $nginxAction->rollback = 'remove domain ' . $data->domain;
        $sideEffects[] = $nginxAction;

        return $sideEffects;
    }
}

class SideEffectRunner
{
    private $fileIO;
    private $databaseIO;
    private $nginxIO;
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
        // NB: Don't catch exceptions here, because it would be fatal failure.
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
    public function actionInstall($installationId)
    {
        $data = new InstalltionData($installationId);
        $data->fetch();
        $appInstaller = new AppInstaller(
            new SideEffectFactory(),
            $data
        );
        $effects = $AppInstaller->install();
        $runner = new SideEffectRunner();
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
