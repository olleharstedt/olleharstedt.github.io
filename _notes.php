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
        $this->pipeline = [
            new Query('SELECT * FROM installations WHERE id = ' . $installationId),
            new FilterNull(),
            new Filter(function($result) { return count($result === 1); }),
            new Filter(function($result) { return $result[0]->is_good_installation; }),
            new Query('UPDATE installations SET is_good_installation = 0 WHERE id = ' . $installationId),
            new FilterTrue('Could not update installation'),
            new Output('Success')
        ];
        // TODO: If-statement action?
        // TODO: How to unit-test? How to mock?
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
