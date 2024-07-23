<?php

/**
 * defmacro
 * quote, backquote, antiquote
 * defmacro accepts forms
 *
 * ELISP:
 * (symbol-function 'foo)
 * cl-loop
 */

$sc = <<<SCHEME
(defun times (a b) (* a b))
(defun f (a b) (times b (+ 1 a)))
(if 
    (= (+ 1 1) 2)
    (f 1 2)
    "nooo nop nop"
)
; (php printf p)
; (map (quote +) (quote (1 2 3)))
SCHEME;

abstract class SexprBase
{
    /**
     * @return SplStack<string>
     */
    public function parse(string $sc)
    {
        // Remove comments
        $sc = preg_replace('/;.*$/m', '', $sc);
        // Normalize string
        $sc = trim((string) preg_replace('/[\t\n\r\s]+/', ' ', $sc));
        $current = new SplStack();
        $base = $current;
        $prev = null;
        $history = new SplStack();
        $buffer = '';
        $inside_quote = 0;
        for ($i = 0; $i < strlen($sc); $i++) {
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
                if (!$inside_quote) {
                    $current->push(new Str($buffer));
                    $buffer = '';
                }
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

class Sexpr extends SexprBase
{
    public $env = [];

    public function eval($sexpr)
    {
        if (!is_object($sexpr)) {
            if ((string) intval($sexpr) === $sexpr) {
                return intval($sexpr);
            }
            if (isset($this->env[$sexpr])) {
                $thing = $this->env[$sexpr];
                if ($thing instanceof Fun) {
                    return $this->eval($thing->body);
                }
            }
        }
        if ($sexpr instanceof Str) {
            return $sexpr->s;
        }
        if (is_string($sexpr)) {
            throw new Exception($sexpr);
        }
        $result = 0;
        $op = $sexpr->shift();
        if ($op instanceof SplStack) {
            return $this->eval($op);
        }
        switch ($op) {
            case "php":
                $fn = $sexpr->shift();
                $arg = $this->eval($sexpr->shift());
                call_user_func($fn, $arg);
                break;
            case "=":
                $branch2 = $sexpr->pop();
                $branch1 = $sexpr->pop();
                return ($this->eval($branch1) === $this->eval($branch2));
            case "!=":
                $branch2 = $sexpr->pop();
                $branch1 = $sexpr->pop();
                return ($this->eval($branch1) !== $this->eval($branch2));
            case "and":
                $branch2 = $sexpr->pop();
                $branch1 = $sexpr->pop();
                return ($this->eval($branch1) && $this->eval($branch2));
            case "or":
                $branch2 = $sexpr->pop();
                $branch1 = $sexpr->pop();
                return ($this->eval($branch1) || $this->eval($branch2));
            case "true":
                return 1;
            case "false":
                return 0;
            case "if":
                $branch2 = $sexpr->pop();
                $branch1 = $sexpr->pop();
                $cond = $sexpr->pop();
                if ($this->eval($cond)) {
                    return $this->eval($branch1);
                } else {
                    return $this->eval($branch2);
                }
            case "+":
                $arg1 = $sexpr->shift();
                $arg2 = $sexpr->shift();
                return $this->eval($arg1) + $this->eval($arg2);
            case "*":
                $arg1 = $sexpr->shift();
                $arg2 = $sexpr->shift();
                return $this->eval($arg1) * $this->eval($arg2);
            case "defun":
                $fnName = $sexpr->shift();
                $args = $sexpr->shift();
                $body = $sexpr->shift();
                $this->env[$fnName] = new Fun($fnName, $args, $body);
                break;
            case "map":
                $fn   = $this->eval($sexpr->shift());
                $list = $this->eval($sexpr->shift());
                foreach ($list->body as $elem) {
                }
                break;
            case "quote":
            case "'":
                $body = $sexpr->shift();
                return new Quote($body);
                break;
            default:
                if (isset($this->env[$op])) {
                    $fn = $this->env[$op];
                    foreach ($fn->args as $arg) {
                        $this->replaceArg($arg, $sexpr->shift(), $fn->body);
                    }
                    return $this->eval($fn->body);
                } else {
                    throw new RuntimeException('Unsupported operation: ' . $op);
                }
                break;
        }
    }

    /**
     * Recursively replace $arg inside $body with $replaceWith
     *
     * @return void
     */
    public function replaceArg($arg, $replaceWith, SplStack $body)
    {
        foreach ($body as $key => $node) {
            if ($node === $arg) {
                $body->offsetSet(count($body) - $key - 1, $replaceWith);
            }
            if ($node instanceof SplStack) {
                $this->replaceArg($arg, $replaceWith, $node);
            }
        }
    }
}

class Str
{
    public $s;
    public function __construct($s)
    {
        $this->s = $s;
    }
}

class Fun
{
    public $name;
    public $args;
    public $body;
    public function __construct($n, $args, $b)
    {
        $this->name = $n;
        $this->args = $args;
        $this->body = $b;
    }
    public function run()
    {
    }
}

class Macro
{
    public $name;
}

class Quote
{
    public $body;
    public function __construct($b)
    {
        $this->body = $b;
    }
}

$s = new Sexpr();
$sexp = $s->parse($sc);
while ($sex = $sexp->shift()) {
    $result = $s->eval($sex);
    var_dump($result);
    if (count($sexp) === 0) {
        break;
    }
}
