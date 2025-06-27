<?php

class DoAThingCommand2
{
    private $db;

    public function __constructor(Db $db)
    {
        $this->db = $db;
    }
    
    public function run(array $data): void
    {
        if ($data['foo'] == 'bar') {
            $sql = ''; // omitted
            $this->db->update($sql);
        }
    }
}

interface Effect {}

class QueryEffect implements Effect
{
    private $sql;

    public function __constructor(string $sql)
    {
        $this->sql = $sql;
    }
}

class DoAThingCommand
{
    public function __invoke(array $data): void
    {
        if ($data['foo'] == 'bar') {
            $sql = ... // omitted
            $result = Fiber::suspend(new QueryEffect($sql));
            echo 'Database query returned the value: ' . $result, PHP_EOL;
        }
    }
}

$fiber = new Fiber(new DoAThingCommand());
$data = [
    'foo' => 'bar'
];
$value = $fiber->start($data);
while (!$fiber->isTerminated()) {
    $data = null;
    switch (get_class($value)) {
        case 'QueryEffect':
            $data = 'Db value';
            break;
    }
    if ($data) {
        $value = $fiber->resume($data);
    }
}
