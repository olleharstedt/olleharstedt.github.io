<?php

class SqlQueryEffect {
    public function __constructor($sql)
    {
    }
}

class DoAThingCommand
{
    public function __invoke(array $data): int
    {
        print_r($data);
        if ($data['foo'] == 'bar') {
            $sql = "SELECT * FROM bla";
            $result = Fiber::suspend(new SqlQueryEffect($sql));
            print_r($result);
            return array_reduce($result, fn($i, $s) => $i + $s);
        }
        return null;
    }
}
