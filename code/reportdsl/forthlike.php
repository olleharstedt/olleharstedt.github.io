<?php

$s = <<<FORTH
report:
    title: "Lagerrapport"
    table: "articles"
    join:
        table: "categories"
        on: "articles.cat_id" = "categories.id"
    end
    columns:
        column:
            title: "Art nr"
            select: "articles.id"
        end
        column:
            title: "Margin percentage"
            as: margin_perc
            select: ( 100 1 "purchase_price" "selling_price" / - * 2 round )
        end
    end
end
FORTH;

// Factor out more words for clarity:
// 17:11 < zelgomer> : compliment  1 swap - ;  : %  100 * 2 round ;  purchase selling / compliment %
// TODO: Add `:` as a word?


// https://termbin.com/jyyq
/*
: {     ['] DEPTH COMPILE,  ['] >R COMPILE, ;  IMMEDIATE
: (})   2 + DEPTH <> ABORT" Unbalanced expression" ;
: }     ['] R> COMPILE,  ['] (}) COMPILE, ;  IMMEDIATE

: TEST
    { 1 2 + } .
    { 5 { 1 2 + } * } . ;

.( TEST ) TEST CR

: FAIL-TEST
    { 1 2 3 + } ;
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
        echo ($word);
        echo "\n";
        // String
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

class Dict extends ArrayObject
{
    public function addWord(string $word, callable $fn)
    {
        $this[$word] = $fn;
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

    /**
     * Parsing the string buffer populates the environment, which is returned.
     */
    public function getSelectFromColumns(Array_ $cols)
    {
        $sql = '';
        foreach ($cols as $col) {
            print_r($col->data);
            $sql .= trim($col->data['select:'], '"') . ', ';
        }
        return trim(trim($sql), ',');
    }

    public function getQuery()
    {
        $stack = getStackFromBuffer($this->buffer, $this->dict);
        $report = $stack->pop();
        $select = $this->getSelectFromColumns($report->data['columns']);
        $table = $report->data['table:'];
        $sql  = "SELECT $select FROM $table";
        return $sql;
    }
}

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

$mainDict = new Dict();

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
$structword = function($stack, $buffer, $word) {
    $struct = new Struct();
    $struct->name = trim($word, ':');
    $stack->push($struct);
};
$mainDict->addWord('report:', $structword);
$mainDict->addWord('join:', $structword);
$mainDict->addWord('column:', $structword);
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
$mainDict->addWord('as:', $datapropword);
$mainDict->addWord('on:', function($stack, $buffer, $word) {
    $struct = $stack->pop();
    $struct->data[$word] = $buffer->next() . $buffer->next() . $buffer->next();
    $stack->push($struct);
});
$mainDict->addWord('select:', function($stack, $buffer, $word) use ($sqlDict) {
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

$report = new ReportForth(new StringBuffer($s), $mainDict);
$query = $report->getQuery();
var_dump($query);
