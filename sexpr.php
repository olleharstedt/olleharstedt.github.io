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

$f = <<<FORTH
"Lagerrapport" title report
"articles" table report
"Art nr" title column
"articles.article_id" select column
FORTH;

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

//$sc = <<<SCHEME
//(+ (- 1 2) (+ 2 3))
//SCHEME;

// Explode by ()?

abstract class SexprBase
{
    public function parse(string $sc)
    {
        $sc = trim(preg_replace('/[\t\n\r\s]+/', ' ', $sc));
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
    public function mathEval($sexpr)
    {
        if (is_string($sexpr)) {
            return intval($sexpr);
        }
        $result = 0;
        $op = $sexpr->shift();
        if ($op instanceof SplStack) {
            return mathEval($op);
        }
        switch ($op) {
            case '+':
                $arg1 = $sexpr->shift();
                $arg2 = $sexpr->shift();
                return mathEval($arg1) + mathEval($arg2);
                break;
            case '-':
                $arg1 = $sexpr->shift();
                $arg2 = $sexpr->shift();
                return mathEval($arg1) - mathEval($arg2);
                break;
            default:
                return intval($op);
        }
        return $result;
    }
}

class ReportSexpr extends SexprBase
{
    public function findFirst(SplStack $sexp, string $symbol)
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

    public function findAll(SplStack $sexp, string $symbol)
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

    public function evalSelect($top)
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

    public function getSelect(SplStack $columns)
    {
        $columns = $this->findAll($columns, 'column');
        $sql = '';
        foreach ($columns as $column) {
            $select = $this->findFirst($column, 'select');
            $sql .= $this->evalSelect($select->top());
            $as = $this->findFirst($column, 'as');
            if ($as) {
                $sql .= ' AS ' . $as->top();
            }
            $sql .= ', ';
        }
        return trim(trim($sql), ',');
    }

    public function getQuery(SplStack $sexp)
    {
        $report = $this->findFirst($sexp, 'report');
        $table = $this->findFirst($report, 'table');
        $columns = $this->findFirst($report, 'columns');
        $select = $this->getSelect($columns);
        $sql  = "SELECT $select FROM {$table->pop()} ";
        return $sql;
    }
}

$report = new ReportSexpr();
echo $report->getQuery($report->parse($sc));
echo "\n";
