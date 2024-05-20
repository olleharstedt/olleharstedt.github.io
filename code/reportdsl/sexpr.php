<?php

/**
 * Four alternatives:
 *   - S-expression
 *   - Forth-like
 *   - JSON
 *   - PHP token_get_all + parser (hard?) SQL subset check? JSON based.
 */

// ROUND((1 - (purchase_price / selling_price)) * 100, 2) AS margin_percent

$sc = <<<SCHEME
(report
    (title "Lagerrapport")
    (table "articles")
    (join 
        (table "categories")
        (on "articles.cat_dn" "categories.dn")
    )
    (columns
        (column 
            (title "Art nr")
            (select "articles.article_id")
        )   
        (column 
            (title "Diff")
            (css "right-align")
            (select (- "articles.article_selling_price" "articles.article_purchase_price"))
            (as "diff")
        )
        (column 
            (title "Diff perc")
            (css "right-align")
            (select (round (* 100 (- 1 (/ purchase_price selling_price))) 2))
            (as "diff_perc")
        )
    )
)
SCHEME;

$json = <<<JAVASCRIPT
{
    "title": "Lagerrapport",
    "table": "articles",
    "join": {
        "table": "categories",
        "on": ["articles.cat_dn", "categories.dn"]
    },
    "columns": [
        {
            "title": "Art nr",
            "select": "articles.id",
        },
        {
            "title": "Diff",
            "css": "right-align",
            "select": {
                "op": "-",
                "args": ["aricles.selling_price", "articles.purchase_price"]
            },
            "as": "diff"
        },
        {
            "title": "Diff perc",
            "css": "right-align",
            // ROUND((1 - (purchase_price / selling_price)) * 100, 2) AS margin_percent
            "select": {
                "op": "round
                "args": [
                    {
                        "op": "*",
                        "args": [
                            100,
                            {
                                "op": "-",
                                "args": [
                                    1,
                                    {
                                        "op": "/",
                                        "args": ["purchase_price", "selling_price"]
                                    }
                                ]
                            }
                        ]
                    },
                    2
                ]
            },
            "as": "diff_perc"
        }
    ]
}
JAVASCRIPT;

//$sc = <<<SCHEME
//(+ (- 1 2) (+ 2 3))
//SCHEME;

// Explode by ()?

abstract class SexprBase
{
    /**
     * @return SplStack<string>
     */
    public function parse(string $sc)
    {
        $sc = trim((string) preg_replace('/[\t\n\r\s]+/', ' ', $sc));
        $len = strlen($sc);
        $current = new SplStack();
        $base = $current;
        // TODO: Why needed?
        $prev = null;
        $history = new SplStack();
        $buffer = '';
        $inside_quote = 0;
        for ($i = 0; $i < $len; $i++) {
            $char = $sc[$i];
            if ($char === '(') {
                $prev = $current;
                $history->push($current);
                $current = new SplStack();
                $prev->push($current);
            } elseif ($char === ')') {
                if ($buffer) {
                    $current->push($buffer);
                    $buffer = '';
                }
                $current = $history->pop();
            } elseif ($char === '"') {
                $inside_quote = 1 - $inside_quote;
            } elseif ($char === ' ' && !$inside_quote) {
                if ($buffer !== '') {
                    $current->push($buffer);
                    $buffer = '';
                }
            } else {
                $buffer .= $char;
            }
        } 
        return $base;
    }
}

class MathSexpr extends SexprBase
{
    /**
     * @param SplStack<mixed>|string $sexpr
     */
    public function mathEval($sexpr): int
    {
        if (is_string($sexpr)) {
            return intval($sexpr);
        }
        $result = 0;
        $op = $sexpr->shift();
        if ($op instanceof SplStack) {
            return $this->mathEval($op);
        }
        switch ($op) {
            case '+':
                $arg1 = $sexpr->shift();
                $arg2 = $sexpr->shift();
                return $this->mathEval($arg1) + $this->mathEval($arg2);
            case '-':
                $arg1 = $sexpr->shift();
                $arg2 = $sexpr->shift();
                return $this->mathEval($arg1) - $this->mathEval($arg2);
            default:
                return intval($op);
        }
    }
}

class ReportSexpr extends SexprBase
{
    /**
     * @param SplStack<mixed> $sexp
     * @return ?SplStack<mixed>
     */
    public function findFirst(SplStack $sexp, string $symbol): ?SplStack
    {
        foreach ($sexp as $s) {
            if ($s instanceof SplStack) {
                if ($s->bottom() === $symbol) {
                    return $s;
                }
            }
        }
        return null;
    }

    /**
     * @param SplStack<mixed> $sexp
     * @return array<mixed>
     */
    public function findAll(SplStack $sexp, string $symbol): array
    {
        $result = [];
        foreach ($sexp as $s) {
            if ($s instanceof SplStack) {
                if ($s->bottom() === $symbol) {
                    $result[] = $s;
                }
            }
        }
        return $result;
    }

    /**
     * @param SplStack<mixed>|string $top
     */
    public function evalSelect($top): string
    {
        $sql = '';
        switch (gettype($top)) {
            case 'string':
                $sql .= $top;
                break;
            case 'object':
                $op = $top->bottom();
                switch ($op) {
                    case '-':
                        $sql .= '(' . $this->evalSelect($top->pop()) . ' - ' . $this->evalSelect($top->pop()) . ')';
                        break;
                }
                break;
        }
        return $sql;
    }
    /**
     * @param SplStack<mixed> $columns
     */
    public function getSelect($columns): string
    {
        $columns = $this->findAll($columns, 'column');
        $sql = '';
        foreach ($columns as $column) {
            $select = $this->findFirst($column, 'select');
            if ($select === null) {
                throw new RuntimeException('Found no select');
            }
            $sql .= $this->evalSelect($select->top());
            $as = $this->findFirst($column, 'as');
            if ($as) {
                $sql .= ' AS ' . $as->top();
            }
            $sql .= ', ';
        }
        return trim(trim($sql), ',');
    }

    /**
     * @param SplStack<mixed> $sexp
     */
    public function getQuery($sexp): string
    {
        $report = $this->findFirst($sexp, 'report');
        if ($report === null) {
            throw new RuntimeException('Found no report');
        }
        $table = $this->findFirst($report, 'table');
        if ($table === null) {
            throw new RuntimeException('Found no table');
        }
        $columns = $this->findFirst($report, 'columns');
        if ($columns === null) {
            throw new RuntimeException('Found no columns');
        }
        $select = $this->getSelect($columns);
        $sql  = "SELECT $select FROM {$table->pop()} ";
        return $sql;
    }
}

/*
$report = new ReportSexpr();
echo $report->getQuery($report->parse($sc));
echo "\n";
*/

$f = <<<FORTH
struct report
    title "Lagerrapport"
    table "articles"
    struct join
        table "categories"
        on "articles.cat_dn" "categories.dn"
    end
    array columns
        struct column
            title "Art nr"
            select "articles.article_id"
        end
        struct column
            title "Diff"
            as "diff"
            css "right-align"
            select (
                "articles.article_selling_price" - "articles.article_purchase_price"
            )
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

    public function getEnvFromBuffer()
    {
        $env = [];
        $stack  = new SplStack();
        while ($word = $this->buffer->next()) {
            if (trim($word, '"') !== $word) {
                $stack->push($word);
            } elseif (ctype_digit($word)) {
                // todo floats
                $stack->push($word);
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

$s = <<<FORTH
1 2 3 + +
FORTH;

/*
$r = new ReportForth(new StringBuffer($s));
$r->addWord('+', function($stack, $buffer) {
    $stack->push($stack->pop() + $stack->pop());
});
$r->getQuery();
 */

$s = <<<FORTH
struct report
    title: Lagerrapport
    table: "articles"
    struct join
        table: "categories"
    end
    array columns
        struct column
            title: "Art nr"
            select: "articles.id"
        end
        struct column
            title: "Margin"
            as: "margin"
            select: ( purchase_price selling_price / 1 - 100 * 2 round )
        end
    end
end
FORTH;

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
//$env = $r->getQuery();
//var_dump($env);

function validate_token(string $token)
{
    if (ctype_alnum(str_replace(['_'], '', $token))) {
        // OK
    } elseif ($token === ')'
        || $token === '('
        || $token === '-'
        || $token === '+'
        || $token === '/'
        || $token === ''
        || $token === ','
        || $token === '*') {
        // OK
    } else {
        throw new RuntimeException('Invalid token: ' . json_encode($token));
    }
}

$src = 'ROUND((1 - (purchase_price / selling_price)) * 100, 2)';
$tokens = token_get_all('<?php ' . $src);
$sql = '';
foreach ($tokens as $token)
{
    if (is_string($token)) {
        validate_token(trim($token));
        $sql .= trim($token);
    } elseif (is_array($token)) {
        if ($token[1] === '<?php ') {
        } else {
            validate_token(trim($token[1]));
            $sql .= trim($token[1]);
        }
    }
}
echo $sql . "\n";
