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
     * @param int $installationId
     * @return void
     */
    public function actionInstall2(int $installationId)
    {
        pipe(
            new DatabaseIOAction('SELECT * FROM installations WHERE id = ' . $installationId),
            function ($result) {
                if (is_null($result)) {
                    throw new Exception('Found no installation');
                }
                if ($result->is_good_installation) {
                    pipe(new DatabaseIOAction( 'UPDATE installations SET is_good_installation = 0 WHERE id = ' . $result->id));
                    pipe(function($result) {
                        if ($result) {
                            pipe(new EchoIOAction('Success'));
                        } else {
                            pipe(new EchoIOAction('Could not update installation'));
                        }
                    });
                }
            }
        );
    }
}
