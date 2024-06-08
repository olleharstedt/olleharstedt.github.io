<?php

/*
$s = <<<FORTH
: new-report var report new table report ! ;
: set-title report @ swap set title ;
: set-table report @ swap set table ;
new-report
"Lagerrapport" set-title
"articles" set-table
FORTH;
*/

// var a 
// new array a !
// "asd" a push

$s = <<<FORTH
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

var columns
new stack columns !

var column
new table column !
column @ "Artnr" set title
column @ "article_id" set select
columns @ column @ push

new table column !
column @ "Diff" set title
column @ "diff" set as
columns @ column @ push

report @ columns @ set columns
unset columns
unset column
FORTH;

// column @ ( 100 1 "purchase_price" "selling_price" / - * 2 round ) set select

/*
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
function getStackFromBuffer(StringBuffer $buffer, Dicts $dicts): SplStack
{
    $stack  = new SplStack();
    while ($word = $buffer->next()) {
        $dict = $dicts->getCurrentDict();
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

class Dicts
{
    public $dicts = [];
    public $currentDict = 'main';

    public function addDict(string $name, Dict $d)
    {
        $this->dicts[$name] = $d;
    }

    public function setCurrent(string $name)
    {
        $this->currentDict = $name;
    }

    public function getCurrentDict()
    {
        return $this->dicts[$this->currentDict];
    }
}

class Array_ extends ArrayObject
{
    public $name;
}

// Memory to save variables etc in
$mem = new ArrayObject();
$dicts = new Dicts();

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
$dicts->addDict('sql', $sqlDict);

$rootDict = new Dict();
$rootDict->addWord('only', function ($stack, $buffer, $word) {
    $dictname = $buffer->next();
});
$dicts->addDict('root', $rootDict);

$mainDict = new Dict();
$mainDict->addWord('swap', function ($stack, $buffer, $word) {
    $a = $stack->pop();
    $b = $stack->pop();
    $stack->push($a);
    $stack->push($b);
});
$mainDict->addWord('dup', function ($stack, $buffer, $word) {
    $a = $stack->pop();
    $stack->push(clone $a);
    $stack->push(clone $a);
});

$mainDict->addWord('.', function ($stack, $buffer, $word) {
    $a = $stack->pop();
    echo $a;
});

$mainDict->addWord('+', function ($stack, $buffer, $word) {
    $a = $stack->pop();
    $b = $stack->pop();
    $stack->push($a + $b);
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

// Remove variable from memory
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

    $mainDict->addWord($name, function ($stack, $buffer, $_word) use ($mainDict, $wordsToRun) {
        $b = new StringBuffer(implode(' ', $wordsToRun));
        while ($word = $b->next()) {
            // TODO: Add support for string
            if (ctype_digit($word)) {
                $stack->push($word);
            } else {
                $fn = $mainDict[$word];
                $fn($stack, $b, $word);
            }
        }
    });
});
$dicts->addDict('main', $mainDict);

$phpDict = new Dict();
$phpDict->addWord('count', function($stack, $buffer, $word) {
    $next = $buffer->next();
    // todo
});

$sqlDict->addWord(':', function ($stack, $buffer, $word) use ($sqlDict) {
    $wordsToRun = [];
    while (($w = $buffer->next()) !== ';') {
        $wordsToRun[] = $w;
    }

    $name = $wordsToRun[0];
    unset($wordsToRun[0]);

    $sqlDict->addWord($name, function ($stack, $buffer, $_word) use ($sqlDict, $wordsToRun) {
        $b = new StringBuffer(implode(' ', $wordsToRun));
        while ($word = $buffer->next()) {
        //while (($w = $b->next()) !== ';') {
        //foreach ($wordsToRun as $word) {
            // TODO: Add support for string
            if (ctype_digit($word)) {
                $stack->push($word);
            } else {
                $fn = $sqlDict[$word];
                $fn($stack, $b, $word);
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
$stack = getStackFromBuffer(new StringBuffer($s), $dicts);
//echo $stack->pop();
//echo "\n";
print_r($mem);
//print_r($mem['report']['joins'][0]['table']);
