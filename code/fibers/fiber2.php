<?php

class SqlQueryEffect
{
    public function __constructor($sql)
    {
    }
}

class Db
{
    public function select($sql)
    {
        return [0, 0, 0];
    }
}

class DoAThingCommand
{
    public function __invoke(array $data): ?int
    {
        if ($data['foo'] == 'bar') {
            $sql = "SELECT * FROM bla";
            $result = Fiber::suspend(new SqlQueryEffect($sql));
            return array_reduce($result, fn($i, $s) => $i + $s);
        }
        return null;
    }
}

class DoAThingCommandInj
{
    private $db;

    public function __constructor(Db $db)
    {
        $this->db = $db;
    }
    
    public function __invoke(array $data): ?int
    {
        if ($data['foo'] == 'bar') {
            $sql = "SELECT * FROM bla";
            $result = $this->db->select($sql);
            return array_reduce($result, fn($i, $s) => $i + $s);
        }
        return null;
    }
}
