<?php

class SqlQueryEffect
{
    public string $sql;
    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }
}

class Db
{
    public function select(string $sql): mixed
    {
        return [0, 0, 0];
    }
}

class DoAThingCommand
{
    /** @param array<mixed> $data */
    public function __invoke(array $data): ?int
    {
        if ($data['foo'] == 'bar') {
            $sql = "SELECT * FROM bla";
            $result = Fiber::suspend(new SqlQueryEffect($sql));
            return (int) array_reduce($result, fn($i, $s) => $i + $s);
        }
        return null;
    }
}

class DoAThingCommandInj
{
    /** @var Db */
    private $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }
    
    /** @param array<mixed> $data */
    public function __invoke(array $data): ?int
    {
        if ($data['foo'] == 'bar') {
            $sql = "SELECT * FROM bla";
            $result = $this->db->select($sql);
            return (int) array_reduce($result, fn($i, $s) => $i + $s);
        }
        return null;
    }
}

class MockEffectHandler
{
    /** @array */
    public $effects = [];

    public function run($command): mixed
    {
        $fiber = new Fiber(new DoAThingCommand());
        $data = ['foo' => 'bar'];
        $effect = $fiber->start($data);
        $i = 0;
        while (!$fiber->isTerminated()) {
            [$type, $res] = $this->effects[$i];
            $effect = $fiber->resume();
        }
        return $fiber->getReturn();
    }
}
