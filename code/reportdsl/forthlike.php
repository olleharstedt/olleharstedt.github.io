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
            select: ( "purchase_price" "selling_price" / 1 - 100 * 2 round )
        end
    end
end
FORTH;

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
        // Two extra space for the while-loop to work
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

class ReportForth
{
    /** @var StringBuffer */
    private $buffer;

    /** @var array<string, function> */
    private $dict = [];

    public function __construct(StringBuffer $b)
    {
        $this->buffer = $b;
    }

    /**
     * Parsing the string buffer populates the environment, which is returned.
     */
    public function getEnvFromBuffer()
    {
        $env = [];
        $stack  = new SplStack();
        while ($word = $this->buffer->next()) {
            echo ($word);
            echo "\n";
            // String
            if (trim($word, '"') !== $word) {
                $stack->push($word);
            // Digit
            } elseif (ctype_digit($word)) {
                // todo floats
                $stack->push($word);
            // Dict word
            } elseif ($this->dict[$word]) {
                $fn = $this->dict[$word];
                // Execute word
                $fn($stack, $this->buffer, $env, $word);
            } else {
                throw new RuntimeException('Word is not a string, not a number, and not in dict: ' . $word);
            }
            // if word is symbol
            // if word is number
            // if word is string
        }
        return $env;
    }

    public function getSelectFromColumns(Array_ $cols)
    {
        $sql = '';
        foreach ($cols as $col) {
            $sql .= trim($col->data['select:'], '"') . ', ';
        }
        return trim(trim($sql), ',');
    }

    public function getQuery()
    {
        $env = $this->getEnvFromBuffer();
        $report = $env['report'];
        $select = $this->getSelectFromColumns($report->data['columns']);
        $table = $report->data['table:'];
        $sql  = "SELECT $select FROM $table";
        return $sql;
    }

    public function addWord($word, $fn)
    {
        $this->dict[$word] = $fn;
    }
}

/*
$s = <<<FORTH
1 2 3 + +
FORTH;
 */

/*
$r = new ReportForth(new StringBuffer($s));
$r->addWord('+', function($stack, $buffer) {
    $stack->push($stack->pop() + $stack->pop());
});
$r->getQuery();
 */

class Struct
{
    public $name;
    public $data = [];
}

class Array_ extends ArrayObject
{
    public $name;
}

$r = new ReportForth(new StringBuffer($s));
// Array is always property?
$r->addWord('array', function($stack, $buffer, &$env, $word) {
    $arr = new Array_();
    $arr->name = $buffer->next();
    $stack->push($arr);
});
$r->addWord('columns:', function($stack, $buffer, &$env, $word) {
    $arr = new Array_();
    $arr->name = trim($word, ':');
    $stack->push($arr);
});
$structword = function($stack, $buffer, &$env, $word) {
    $struct = new Struct();
    $struct->name = trim($word, ':');
    $stack->push($struct);
};
$r->addWord('report:', $structword);
$r->addWord('join:', $structword);
$r->addWord('column:', $structword);
$r->addWord('struct', function($stack, $buffer, &$env, $word) {
    $struct = new Struct();
    $struct->name = $buffer->next();
    $stack->push($struct);
});
$r->addWord('end', function($stack, $buffer, &$env, $word) {
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
            $env[$item->name] = $item;
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
$datapropword = function($stack, $buffer, &$env, $word) {
    $struct = $stack->pop();
    $struct->data[$word] = $buffer->next();
    $stack->push($struct);
};
$r->addWord('title:', $datapropword);
$r->addWord('table:', $datapropword);
$r->addWord('as:', $datapropword);
$r->addWord('on:', function($stack, $buffer, &$env, $word) {
    $struct = $stack->pop();
    $struct->data[$word] = $buffer->next() . $buffer->next() . $buffer->next();
    $stack->push($struct);
});
$r->addWord('select:', function($stack, $buffer, &$env, $word) {
    $next = $buffer->next();
    if ($next === '(') {
        while ($w = $buffer->next() !== ')') {
        }
    } else {
        $struct = $stack->pop();
        $struct->data[$word] = $next;
        $stack->push($struct);
    }
});
$env = $r->getQuery();
var_dump($env);
