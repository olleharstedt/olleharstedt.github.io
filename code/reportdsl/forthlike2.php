<?php

$s = <<<FORTH
: new-report var report new table report ! ;
new-report
FORTH;

// var a 
// new array a !
// "asd" a push
/*

var report
new table report !
report @ "Lagerrapport" set title
report @ "articles" set table
var joins
new stack joins !
var join
new table join !
join @ "categories" set table
join @ "articles.cat_id = categories.id" set on
joins @ join @ push
report @ joins @ set joins
unset joins
unset join


var a
100 const b
"foo" a !
a @ .
"bar" a !
a @ .
b .
var t
new table t !
t @ 10 set foo
t @ "moo" set bar
*/

class StringBuffer
{
    /** @var string */
    private $buffer;

    /** @var int */
    private $pos = 0;

    private $inside_quote = 0;

    public function __construct(string $s)
    {
        // Normalize string
        $s = trim((string) preg_replace('/[\t\n\r\s]+/', ' ', $s));
        // Two extra spaces for the while-loop to work
        $this->buffer = $s. '  ';
    }

    public function next()
    {
        if ($this->buffer[$this->pos] === '"') {
            $this->inside_quote = 1 - $this->inside_quote;
        }
        if ($this->inside_quote === 1) {
            $nextQuote = strpos($this->buffer, '"', $this->pos + 1);
            $result = substr($this->buffer, $this->pos, $nextQuote - $this->pos + 1);
            $this->pos = $nextQuote + 2;
            $this->inside_quote = 0;
        } else {
            $nextSpace = strpos($this->buffer, ' ', $this->pos);
            $result = substr($this->buffer, $this->pos, $nextSpace - $this->pos);
            $this->pos = $nextSpace + 1;
        }
        return $result;
    }
}

/**
 * Main eval loop
 */
function getStackFromBuffer(StringBuffer $buffer, Dict $dict): SplStack
{
    $stack  = new SplStack();
    while ($word = $buffer->next()) {
        error_log($word);
        if (trim($word, '"') !== $word) {
            $stack->push($word);
            // Digit
        } elseif (ctype_digit($word)) {
            $stack->push($word);
            // Execute dict word
        } elseif ($dict[$word]) {
            $fn = $dict[$word];
            $fn($stack, $buffer, $word);
        } else {
            throw new RuntimeException('Word is not a string, not a number, and not in dictionary: ' . $word);
        }
    }
    return $stack;
}

/**
 * Parsing the string buffer populates the environment, which is returned.
 *
 * @return string
 */
function getSelectFromColumns(Array_ $cols)
{
    $sql = '';
    foreach ($cols as $col) {
        $sql .= trim($col->data['select:'], '"') . ', ';
    }
    return trim(trim($sql), ',');
}


class Dict extends ArrayObject
{
    public function addWord(string $word, callable $fn)
    {
        $this[$word] = $fn;
    }

    public function removeWord(string $word)
    {
        unset($this[$word]);
    }
}

class Struct
{
    public $name;
    public $data = [];
}

class Array_ extends ArrayObject
{
    public $name;
}

class ReportForth
{
    /** @var StringBuffer */
    private $buffer;

    /** @var Dict */
    private $dict = [];

    public function __construct(StringBuffer $b, Dict $d)
    {
        $this->buffer = $b;
        $this->dict   = $d;
    }

    public function getQuery()
    {
        $stack = getStackFromBuffer($this->buffer, $this->dict);
        $report = $stack->pop();
        $select = getSelectFromColumns($report->data['columns']);
        $table = $report->data['table:'];
        $sql  = "SELECT $select FROM $table";
        return $sql;
    }
}

// Memory to save variables etc in
$mem = new ArrayObject();

$sqlDict = new Dict();
$sqlDict->addWord('/', function($stack, $buffer, $word) {
    $b = $stack->pop();
    $a = $stack->pop();
    $stack->push('(' . $a . ' / ' . $b . ')');
});
$sqlDict->addWord('-', function($stack, $buffer, $word) {
    $b = $stack->pop();
    $a = $stack->pop();
    $stack->push('(' . $a . ' - ' . $b . ')');
});
$sqlDict->addWord('*', function($stack, $buffer, $word) {
    $b = $stack->pop();
    $a = $stack->pop();
    $stack->push('(' . $a . ' * ' . $b . ')');
});
$sqlDict->addWord('round', function($stack, $buffer, $word) {
    $b = $stack->pop();
    $a = $stack->pop();
    $stack->push('round(' . $a . ', ' . $b . ')');
});

// Word to create new words.
$mainDict = new Dict();
$mainDict->addWord('swap', function ($stack, $buffer, $word) use ($mainDict) {
    $a = $stack->pop();
    $b = $stack->pop();
    $stack->push($a);
    $stack->push($b);
});
$mainDict->addWord('dup', function ($stack, $buffer, $word) use ($mainDict) {
    $a = $stack->pop();
    $stack->push(clone $a);
    $stack->push(clone $a);
});

$mainDict->addWord('.', function ($stack, $buffer, $word) use ($mainDict) {
    $a = $stack->pop();
    echo $a;
});

$mainDict->addWord('+', function ($stack, $buffer, $word) use ($mainDict) {
    $a = $stack->pop();
    $b = $stack->pop();
    $stack->push($a + $b);
});

// Array is always property?
$mainDict->addWord('array', function($stack, $buffer, $word) {
    $arr = new Array_();
    $arr->name = $buffer->next();
    $stack->push($arr);
});
$mainDict->addWord('columns:', function($stack, $buffer, $word) {
    $arr = new Array_();
    $arr->name = trim($word, ':');
    $stack->push($arr);
});
$mainDict->addWord('run-query', function($stack, $buffer, $word) {
    $report = $stack->top();
    $select = getSelectFromColumns($report->data['columns']);
    $table = $report->data['table:'];
    $sql  = "SELECT $select FROM $table";
    $data = [
        'rows' => [
            [
                'id' => 1,
                'diff' => 2,
                'diff_perc' => 11
            ],
            [
                'id' => 2,
                'diff' => 4,
                'diff_perc' => 12
            ],
            [
                'id' => 3,
                'diff' => 6,
                'diff_perc' => 13
            ]
        ]
    ];
    $stack->push($data);
});
$mainDict->addWord('totals:', function($stack, $buffer, $word) {

    $arr = new Array_();
    $arr->name = trim($word, ':');
    $stack->push($arr);
});
$structword = function($stack, $buffer, $word) {
    $struct = new Struct();
    $struct->name = trim($word, ':');
    $stack->push($struct);
};
$mainDict->addWord('report:', $structword);
$mainDict->addWord('join:', $structword);
$mainDict->addWord('column:', $structword);
$mainDict->addWord('total:', $structword);
$mainDict->addWord('struct', function($stack, $buffer, $word) {
    $struct = new Struct();
    $struct->name = $buffer->next();
    $stack->push($struct);
});
$mainDict->addWord('end', function($stack, $buffer, $word) {
    $item = $stack->pop();
    if ($item instanceof Struct) {
        if ($stack->count() > 0) {
            if ($stack->top() instanceof Struct) {
                $parentstruct = $stack->pop();
                $parentstruct->data[$item->name] = $item;
                $stack->push($parentstruct);
            } elseif ($stack->top() instanceof Array_) {
                $parentarray = $stack->pop();
                $parentarray[] = $item;
                $stack->push($parentarray);
            }
        } else {
            $stack->push($item);
        }
    } elseif ($item instanceof Array_) {
        if ($stack->count() > 0) {
            if ($stack->top() instanceof Struct) {
                $parentstruct = $stack->pop();
                $parentstruct->data[$item->name] = $item;
                $stack->push($parentstruct);
            }
        }
    }
});
$datapropword = function($stack, $buffer, $word) {
    $struct = $stack->pop();
    $struct->data[$word] = $buffer->next();
    $stack->push($struct);
};
$mainDict->addWord('title:', $datapropword);
$mainDict->addWord('table:', $datapropword);
$mainDict->addWord('for:', $datapropword);
$mainDict->addWord('as:', $datapropword);
$mainDict->addWord('on:', function($stack, $buffer, $word) {
    $struct = $stack->pop();
    $struct->data[$word] = $buffer->next() . $buffer->next() . $buffer->next();
    $stack->push($struct);
});
$mainDict->addWord('select:', function($stack, $buffer, $word) use ($sqlDict, $mainDict) {
    $next = $buffer->next();

    if ($next === '(') {
        $newBuffer = '';
        while (($w = $buffer->next()) !== ')') {
            $newBuffer .= ' ' . $w;
        }
        $newStack = getStackFromBuffer(new StringBuffer($newBuffer), $sqlDict);
        $struct = $stack->pop();
        $struct->data[$word] = $newStack->pop();
        $stack->push($struct);
    } else {
        $struct = $stack->pop();
        $struct->data[$word] = $next;
        $stack->push($struct);
    }
});
$mainDict->addWord('!', function($stack, $buffer, $word) use ($mem) {
    $name = $stack->pop();
    $value = $stack->pop();
    $mem[$name] = $value;
});
$mainDict->addWord('@', function($stack, $buffer, $word) use ($mem) {
    $varname = $stack->pop();
    $stack->push($mem[$varname]);
});
$mainDict->addWord('var', function($stack, $buffer, $word) use ($mem, $mainDict) {
    $varName = $buffer->next();
    $mainDict->addWord($varName, function($stack, $buffer, $word) use ($mem) {
        $stack->push($word);
    });
});
$mainDict->addWord('unset', function($stack, $buffer, $word) use ($mem, $mainDict) {
    $varName = $buffer->next();
    unset($mem[$varName]);
});
$mainDict->addWord('const', function($stack, $buffer, $word) use ($mainDict) {
    $value = $stack->pop();
    $name = $buffer->next();
    $mainDict->addWord($name, function($stack, $buffer, $word) use ($value) {
        $stack->push($value);
    });
});
$mainDict->addWord('new', function($stack, $buffer, $word) {
    $type = $buffer->next();
    switch ($type) {
        case 'table':
            // Fallthru
        case 'array':
            $stack->push(new ArrayObject());
            break;
        case 'stack':
            $stack->push(new SplStack());
            break;
        default:
            throw new RuntimeException('Unknown type for new: ' . $type);
    }
});
$mainDict->addWord('push', function($stack, $buffer, $word) use ($mainDict) {
    $value = $stack->pop();
    $s = $stack->pop();
    $s->push($value);
});
$mainDict->addWord('pop', function($stack, $buffer, $word) use ($mainDict) {
    $s   = $stack->pop();
    $stack->push($s->pop());
});
$mainDict->addWord('set', function($stack, $buffer, $word) use ($mainDict) {
    $value = $stack->pop();
    $table = $stack->pop();
    $key   = $buffer->next();
    $table[$key] = $value;
});
$mainDict->addWord(':', function ($stack, $buffer, $word) use ($mainDict) {
    $wordsToRun = [];
    while (($w = $buffer->next()) !== ';') {
        $wordsToRun[] = $w;
    }

    $name = $wordsToRun[0];
    unset($wordsToRun[0]);
    $buff = new StringBuffer(implode(' ', $wordsToRun));

    $mainDict->addWord($name, function ($stack, $buffer, $_word) use ($mainDict, $wordsToRun, $buff) {
        $b = $buff;
        while ($word = $b->next()) {
            error_log(' ' . $word);
            // TODO: Add support for string
            if (ctype_digit($word)) {
                $stack->push($word);
            } else {
                $fn = $mainDict[$word];
                $fn($stack, $buff, $word);
            }
        }
    });
});


// TODO: How to give data here?
$doDict = new Dict();
$doDict->addWord('count', function($stack, $buffer, $word) {
    $next = $buffer->next();
});
$mainDict->addWord('do:', function($stack, $buffer, $word) use ($doDict, $mainDict) {
    $next = $buffer->next();

    if ($next === '(') {
        $newBuffer = '';
        while (($w = $buffer->next()) !== ')') {
            $newBuffer .= ' ' . $w;
        }
        $newStack = getStackFromBuffer(new StringBuffer($newBuffer), $doDict);
        $struct = $stack->pop();
        $struct->data[$word] = $newStack->pop();
        $stack->push($struct);
    } else {
        throw new RuntimeException('The do-word requires an expression within ()');
    }
});

$sqlDict->addWord(':', function ($stack, $buffer, $word) use ($sqlDict) {
    $wordsToRun = [];
    while (($w = $buffer->next()) !== ';') {
        $wordsToRun[] = $w;
    }

    $name = $wordsToRun[0];
    unset($wordsToRun[0]);

    $sqlDict->addWord($name, function ($stack, $buffer, $_word) use ($sqlDict, $wordsToRun) {
        foreach ($wordsToRun as $word) {
            // TODO: Add support for string
            if (ctype_digit($word)) {
                $stack->push($word);
            } else {
                $fn = $sqlDict[$word];
                $fn($stack, $buffer, $word);
            }
        }
    });
});
$sqlDict->addWord('swap', function ($stack, $buffer, $word) use ($sqlDict) {
    $a = $stack->pop();
    $b = $stack->pop();
    $stack->push($a);
    $stack->push($b);
});

/*
$s = <<<FORTH
: compliment 1 swap - ;
: % 100 swap * 2 round ;
"purchase_price" "selling_price" / compliment %
FORTH;
*/
$stack = getStackFromBuffer(new StringBuffer($s), $mainDict);
//echo $stack->pop();
//echo "\n";
print_r($mem);
//print_r($mem['report']['joins'][0]['table']);
