<?php

use PHPUnit\Framework\TestCase;

class EffectTest extends TestCase
{
    public static function setupBeforeClass(): void
    {
        require_once("fiber2.php");
    }

    public function testCommand(): void
    {
        $fiber = new Fiber(new DoAThingCommand());
        $data = [
            'foo' => 'bar'
        ];
        $value = $fiber->start($data);
        while (!$fiber->isTerminated()) {
            if ($value instanceof SqlQueryEffect) {
                $queryResult = [1, 2, 3];
                $value = $fiber->resume($queryResult);
            } else {
                $value = $fiber->resume();
            }
        }
        $ret = $fiber->getReturn();
        print_r($ret);
    }
}
