<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;

class EffectTest extends TestCase
{
    public static function setupBeforeClass(): void
    {
        require_once("fiber2.php");
    }

    public function testCommandEffects(): void
    {
        $fiber = new Fiber(new DoAThingCommand());
        $data = ['foo' => 'bar'];
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
        $this->assertEquals($ret, 6);
    }

    public function testCommandEffectHandler(): void
    {
        $handler = new MockEffectHandler(new DoAThingCommand());
        $handler->effects = [
            [1, 2, 3]
        ];
        $ret = $handler->run();
        $this->assertEquals($ret, 6);
    }


    public function testCommandMock(): void
    {
        $db = $this
            ->getMockBuilder(Db::class)
            ->getMock();
        $db->method('select')->willReturn([1, 2, 3]);
        $command = new DoAThingCommandInj($db);
        $data = ['foo' => 'bar'];
        $ret = $command($data);
        $this->assertEquals($ret, 6);
    }
}
