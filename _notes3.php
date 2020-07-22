<?php

class Action
{
    private $n;
    public function __construct($n)
    {
        $this->n = $n;
    }
    public function __invoke()
    {
        echo $this->n;
    }
}

function bar(): Generator
{
    echo 'a';
    yield function() { echo 2;};
    echo 'b';
    yield new Action(3);
    return 6;
}

function foo()
{
    $pipeline = [
        bar(),
        function() { echo 1;},
        (function(): Generator {
            yield new Action(99);
            yield function() {echo 88;};
        })()
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

foreach (foo() as $k => $i) {
    if ($i instanceof Generator) {
        foreach ($i as $k) {
            $k();
            echo PHP_EOL;
        }
        echo $i->getReturn();
    } else {
        $i();
        echo PHP_EOL;
    }
}
