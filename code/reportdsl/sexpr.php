<?php

/**
 * Four alternatives:
 *   - S-expression
 *   - Forth-like
 *   - JSON
 *   - JSON + safe SQL subset
 *   - Report builder, no DSL but rather graphical tools
 */

// ROUND((1 - (purchase_price / selling_price)) * 100, 2) AS margin_percent

$sc = <<<SCHEME
(report
    (title "Stock report")
    (table "articles")
    (join 
        (table "categories")
        (type left)
        (on "articles.cat_id" "categories.id")
    )
    (columns
        (column 
            (title "Art nr")
            (select "articles.article_id")
        )   
        (column 
            (title "Diff")
            (css "right-align")
            (select (- "articles.selling_price" "articles.purchase_price"))
            (as "diff")
        )
        (column 
            (title "Diff perc")
            (css "right-align")
            (select (round (* 100 (- 1 (/ purchase_price selling_price))) 2))
            (as "diff_perc")
        )
    )
    (totals
        (total
            (title "Diff average")
            (as "diff_average")
            (for "diff")
            (do (/ (sum diff) (count rows)))
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
            "select": "ROUND((1 - (purchase_price / selling_price)) * 100, 2)",
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
            case 'int':
                $sql .= $top;
                break;
            case 'string':
                $sql .= $top;
                break;
            case 'object':
                $op = $top->bottom();
                switch ($op) {
                    case '-':
                        $a = $top->pop();
                        $b = $top->pop();
                        $sql .= '(' . $this->evalSelect($b) . ' - ' . $this->evalSelect($a) . ')';
                        break;
                    case '*':
                        $a = $top->pop();
                        $b = $top->pop();
                        $sql .= '(' . $this->evalSelect($b) . ' * ' . $this->evalSelect($a) . ')';
                        break;
                    case '/':
                        $a = $top->pop();
                        $b = $top->pop();
                        $sql .= '(' . $this->evalSelect($b) . ' / ' . $this->evalSelect($a) . ')';
                        break;
                    case 'round':
                        $a = $top->pop();
                        $b = $top->pop();
                        $sql .= 'ROUND('  
                            . $this->evalSelect($b) . ', ' 
                            . $this->evalSelect($a) . ')';
                        break;
                    default:
                        throw new RuntimeException('Unknown op: ' . $op);
                }
                break;
        }
        return $sql;
    }

    /**
     *
     * (do (/ (sum diff) (count rows)))
     */
    public function evalTotal(SplStack $sexp, array $data): float
    {
        $result = 0;
        $op = $sexp->top();

        return $result;
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
    public function getQuery(SplStack $sexp): string
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

    /**
     *
     */
    public function getTotals(SplStack $sexp, array $data)
    {
        $totals = [];
        $report = $this->findFirst($sexp, 'report');
        if ($report) {
            $totalsNode = $this->findFirst($report, 'totals');
            if ($totalsNode) {
                $totalsNode = $this->findAll($totalsNode, 'total');
                foreach ($totalsNode as $total) {
                    $as = $this->findFirst($total, 'as');
                    if ($as) {
                        $totals[$as->top()] = $this->evalTotal($this->findFirst($total, 'do'), $data);
                    }
                }
            }
        }
        return $totals;
    }
}

$report = new ReportSexpr();
$parsed = $report->parse($sc);
echo $report->getQuery($parsed);
// todo Use query to get data
$data = [
    [
        'diff' => 1,
        'diff_perc' => 11
    ],
    [
        'diff' => 2,
        'diff_perc' => 12
    ],
    [
        'diff' => 3,
        'diff_perc' => 13
    ]
];
var_dump($report->getTotals($parsed, $data));
echo "\n";
die;

/**
 * Throws exception if token is not allowed.
 */
function validate_token(string $token)
{
    $allowed_words = [
        'ROUND',
        'purchase_price',
        'selling_price'
    ];
    if (ctype_alnum(str_replace(['_'], '', $token))) {
        if ((string) intval($token) === $token) {
            // OK
        } elseif (in_array($token, $allowed_words)) {
            // OK
        } else {
            throw new RuntimeException('Token is not in whitelist: ' . json_encode($token));
        }
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
