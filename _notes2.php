<?php

class Query
{
    private $query;
    public function __construct($query)
    {
        $this->query = $query;
    }

    public function __invoke($res)
    {
        echo 'called query ' . $this->query . PHP_EOL;
        $user = new stdClass();
        $user->is_admin = 0;
        $user->id = 1;
        return [$user, []];
    }
}

class Output
{
    private $message;
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function __invoke($res)
    {
        echo 'called output ' .  $this->message . PHP_EOL;
        return [$res, []];
    }
}

interface SideEffectFactoryInterface {}

class SideEffectFactory implements SideEffectFactoryInterface
{
    public function query($sql)
    {
        return new Query($sql);
    }
    public function output($message)
    {
        return new Output($message);
    }
}

class Mock implements SideEffectFactoryInterface
{
    private $results;
    private $i = 0;
    public $args = [];
    public function __construct($results)
    {
        $this->results = $results;
    }
    public function __call($name, $args)
    {
        if (!array_key_exists($this->i, $this->results)) {
            throw new Exception('No result at i ' . $this->i);
        }
        $this->args[] = $args;
        return function () { return [$this->results[$this->i++], []]; };
    }
}

function updateUser(int $userId, SideEffectFactoryInterface $make)
{
    return [
        $make->query('SELECT * FROM user WHERE id = ' . $userId),
        function ($result) use ($userId, $make) {
            $reverted = $result->is_admin ? 0 : 1;
            return [null, [$make->query(sprintf('UPDATE user SET is_admin = %d WHERE id = %d', $reverted, $userId))]];
        },
        function ($result) use ($make) {
            if ($result) {
                return [true, [$make->output('Success')]];
            } else {
                return [false, [$make->output('Could not update user')]];
            }
        }
    ];
}

function updateUser2(int $userId, SideEffectFactoryInterface $make)
{
    return [
        $make->query('SELECT * FROM user WHERE id = ' . $userId),
        function ($user) use ($make) {
            $reverted = $user->is_admin ? 0 : 1;
            return [
                null,
                $make->query(
                    sprintf(
                        'UPDATE user SET is_admin = %d WHERE id = %d',
                        $reverted,
                        $user->id
                    )
                )
            ];
        },
        function ($result) use ($make) {
            $msg = $result ? 'Success' : 'Could not update user';
            return [
                $result,
                $make->output($msg)
            ];
        }
    ];
}

function resolveDependencies($action)
{
    $action;
}

function run($result, array $actions)
{
    foreach ($actions as $action) {
        resolveDependencies($action);
        list($result, $newActions) = $action($result);
        if (is_array($newActions)) {
            $result = run($result, $newActions);
        } elseif (!empty($newActions)) {
            $result = run($result, [$newActions]);
        }
    }
    return $result;
}

$user = new stdClass();
$user->id = 1;
$user->is_admin = 1;
$mock = new Mock(
    [
        $user,  // User query
        true,   // Update query
        null      // Output
    ]
);
$actions = updateUser2(1, $mock);
//$actions = updateUser2(1, new SideEffectFactory());
$result = run(null, $actions);
var_dump($mock->args);
