<?php

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

        $this->actions[] = function ($result) {
            if (is_null($result)) {
                throw new Exception('Found no installation');
            }
            if ($result->is_good_installation) {
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
        };
    }

    /**
     * Using pipe() to add action to queue.
     *
     * pipe() accepts any number of Action, string (wraps in EchoIOAction) or
     * lambda (wraps in FunctionAction).
     *
     * @param int $installationId
     * @return Action[]
     */
    public function actionUpdateInstallation(int $installationId)
    {
        return [
            new DatabaseIOAction('SELECT * FROM installations WHERE id = ' . $installationId),
            new FilterNullAction(),
            new FilterAction(function($result) { return count($result === 1); }),
            new FilterAction(function($result) { return $result[0]->is_good_installation; }),
            new DatabaseIOAction('UPDATE installations SET is_good_installation = 0 WHERE id = ' . $result->id),
            new FilterSuccessAction('Success', 'Could not update installation')
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
    }
}
