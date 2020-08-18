<?php

class Action
{
    private $n;
    public function __construct($n)
    {
        $this->n = $n;
    }
    public function __invoke($arg)
    {
        echo $this->n;
    }
}

function bar($arg)
{
    echo 'a';
    yield function($arg) { echo $arg; return $arg + 1; };
    yield function($arg) { echo $arg; return $arg + 1; };
    yield function($arg) { echo $arg; return $arg + 1; };
    echo 'b';
    yield new Action(3);
    return 6;
}

function foo($arg)
{
    echo $arg;
    $pipeline = [
        bar($arg),
        function($arg) { echo 1;},
        function($arg) {
            yield new Action(99);
            yield function($arg) {echo 88;};
        }
    ];

    yield from $pipeline;

    /*
    foreach ($pipeline as $f) {
        if (is_iterable($f)) {
            foreach ($f() as $g) {
                yield from $g;
            }
        } else {
            yield $f;
        }
    }
     */
}
$arg = 0;

foreach (foo($arg) as $k => $i) {
    if ($i instanceof Generator) {
        foreach ($i as $k) {
            $arg = $k($arg);
            echo PHP_EOL;
        }
        echo $i->getReturn();
    } else {
        $s = $i($arg);
        if ($s instanceof Generator) {
            foreach ($s as $k) {
                $k($arg);
                echo PHP_EOL;
            }
            echo $s->getReturn();
        } else {
            echo PHP_EOL;
        }
    }
}
