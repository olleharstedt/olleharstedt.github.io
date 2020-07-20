<?php

// https://github.com/thephpleague/pipeline
// StageInterface

class InstallController
{
    /** @var array<int, IOAction|callable> */
    public $actions = [];

    /**
     * Manually putting actions in queue.
     *
     * @param int $installationId
     * @return void
     */
    public function actionInstall(int $installationId)
    {
        $this->actions[] = new DatabaseIOAction(
            'SELECT * FROM installations WHERE id = ' . $installationId
        );
        $this->actions[] = function ($result) { return !is_null($result); };
        $this->actions[] = function ($result) { return count($result) === 1; };
        $this->actions[] = function ($result) { return $result[0]->is_good_installation; };
        $that->actions[] = new DatabaseIOAction(
            'UPDATE installations SET is_good_installation = 0 WHERE id = ' . $result->id
        );
        // TODO: Can't add to actions in lambda.
        $that->actions[] = function($result) {
            if ($result) {
                $this->actions[] = new EchoIOAction('Success');
            } else {
                $this->actions[] = new EchoIOAction('Could not update installation');
            }
        };
    }

    /**
     * Using pipe() to add action to queue.
     *
     * pipe() accepts any number of Action, string (wraps in EchoIOAction) or
     * lambda (wraps in FunctionAction).
     *
     * @param int $installationId
     * @return void
     */
    public function actionUpdateInstallation(int $installationId)
    {
        $this->actions = [
            new Query('SELECT * FROM installations WHERE id = ' . $installationId),
            new FilterNull('Found no installation with this id'),
            new Filter(function($result) { return count($result === 1); }),
            new Filter(function($result) { return $result[0]->is_good_installation; }),
            new Query('UPDATE installations SET is_good_installation = 0 WHERE id = ' . $installationId),
            new FilterTrue('Could not update installation'),
            new Output('Success')
        ];
        // TODO: If-statement or switch action?
        // TODO: How to unit-test? How to mock?
        // TODO: Becomes a DSL?
        // TODO: Hard to follow program flow?
    }

    public function updateUser(int $installationId)
    {
        return [
            new Query('SELECT * FROM user WHERE id = ' . $installationId),
            function ($result) {
                if ($result->is_admin) {
                    return [null, [new Query('UPDATE user SET is_admin = 0')]];
                } else {
                    return [null, [new Query('UPDATE user SET is_admin = 1')]];
                }
            },
            function ($result) {
                if ($result) {
                    return [null, [new Output('Success')]];
                } else {
                    return [null, [new Output('Could not update user')]];
                }
            }
        ];
    }
}

class Pipeline
{
    public function run($result, $actions)
    {
        foreach ($actions as $action) {
            $this->resolveDependencies($action);
            list($result, $newActions) = $action($result);
            $result = $this->run($result, $actions);
        }
        return $result;
    }
}

class InstallationTest
{
    public function testUpdate()
    {
        $contr = new InstallController();
        $actions = $contr->actionUpdateInstallation(1);
        list($one, $two, $three) = $actions;
        // TODO: Fragile, if we add another filter, without changing outcome?
    }
}
