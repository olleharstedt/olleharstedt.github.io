<?php

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
report struct
    "Lagerrapport" title
    "articles" table
    join struct
        "categories" table
        "articles.cat_dn" "categories.dn" on
    end
    columns struct
        column struct
            "Art nr" title
            "articles.article_id" select
        end
        column struct
            "Diff" title
            "right-align" css
            "articles.article_selling_price" "articles.article_purchase_price" minus select
            "diff" as
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

    public function __construct(string $s)
    {
        // Normalize string
        $s = trim((string) preg_replace('/[\t\n\r\s]+/', ' ', $s));
        $this->buffer = $s. ' ';
    }

    public function next()
    {
        $nextSpace = strpos($this->buffer, ' ', $this->pos);
        $result = substr($this->buffer, $this->pos, $nextSpace - $this->pos);
        $this->pos = $nextSpace + 1;
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

    public function getQuery()
    {
        $env = [];
        $stack  = new SplStack();
        while ($word = $this->buffer->next()) {
            error_log($word);
            if (trim($word, '"') !== $word) {
                $stack->push($word);
            } elseif (ctype_digit($word)) {
                // todo floats
                $stack->push($word);
            } elseif ($this->dict[$word]) {
                $fn = $this->dict[$word];
                $fn($stack, $this->buffer);
            } else {
                throw new RuntimeException('Word is not a string, not a number, and not in dict: ' . $word);
            }
            // if word is symbol
            // if word is number
            // if word is string
        }
        var_dump($stack);
        return $env;
    }

    public function addWord($word, $fn)
    {
        $this->dict[$word] = $fn;
    }
}

$s = <<<FORTH
1 2 3 + +
FORTH;

$r = new ReportForth(new StringBuffer($s));
$r->addWord('+', function($stack, $buffer) {
    $stack->push($stack->pop() + $stack->pop());
});
$r->getQuery();
