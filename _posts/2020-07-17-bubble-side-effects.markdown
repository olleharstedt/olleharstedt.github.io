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
    public function __construct()
    {
    }

    /**
     * @param IOAction[] $actions
     */
    public function run(array $actions)
    {
    }
}

class InstallController
{
    public function actionInstall()
    {
        $data = new InstalltionData();
        $appInstaller = new AppInstaller(
            new SideEffectFactory(),
            $data
        );
        $effects = $AppInstaller->install();
        $runner = SideEffectRunner();
        $runner->run($effects);
    }
}
```
