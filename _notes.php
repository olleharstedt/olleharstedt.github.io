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
            new SelectOneAction('SELECT * FROM installations WHERE id = ' . $installationId),
            new FilterNullAction(),
            new FilterAction(function($result) { return $result->is_good_installation; }),
            new UpdateTableAction('UPDATE installations SET is_good_installation = 0 WHERE id = ' . $result->id),
            new FilterSuccessAction('Success', 'Could not update installation')
        ];
        // TODO: If-statement action?
    }
}
