<?php

// --- 1. RUNTIME & FIBER-BASED GENERATOR ENGINE ---

class SchemeGenerator {
    private Fiber $fiber;

    public function __construct(array $expressions, array $env) {
        // Run the evaluation inside an isolated PHP Fiber stack
        $this->fiber = new Fiber(function() use ($expressions, $env) {
            $result = null;
            foreach ($expressions as $expr) {
                $result = evaluate($expr, $env);
            }
            return $result;
        });
    }

    public function next(): mixed {
        if ($this->fiber->isTerminated()) {
            return null;
        }
        if (!$this->fiber->isStarted()) {
            return $this->fiber->start();
        }
        return $this->fiber->resume();
    }
}

// --- 2. THE EVALUATOR ---

function evaluate(mixed $ast, array &$env): mixed {
    if (!is_array($ast)) {
        if (is_numeric($ast)) return (float)$ast;
        if (is_string($ast) && str_starts_with($ast, '"') && str_ends_with($ast, '"')) return trim($ast, '"');
        if (array_key_exists($ast, $env)) return $env[$ast];
        return $ast; 
    }

    if (empty($ast)) return null;
    $op = $ast[0];

    switch ($op) {
        case 'define':
            return $env[$ast[1]] = evaluate($ast[2], $env);

        case 'yield':
            $val = evaluate($ast[1], $env);
            // Suspend the current native PHP stack frame and pass the value out
            Fiber::suspend($val);
            return null;

        case 'cond':
            foreach (array_slice($ast, 1) as $clause) {
                if ($clause[0] === 'else' || evaluate($clause[0], $env)) {
                    $res = null;
                    foreach (array_slice($clause, 1) as $expr) {
                        $res = evaluate($expr, $env);
                    }
                    return $res;
                }
            }
            return null;

        case 'let': 
            $loopName = $ast[1];
            $bindings = $ast[2];
            $body = array_slice($ast, 3);
            
            $localEnv = $env;
            foreach ($bindings as $b) {
                $localEnv[$b[0]] = evaluate($b[1], $env);
            }

            $localEnv[$loopName] = function(...$args) use (&$localEnv, $body, $bindings) {
                foreach ($bindings as $i => $b) {
                    $localEnv[$b[0]] = $args[$i];
                }
                $res = null;
                foreach ($body as $expr) { $res = evaluate($expr, $localEnv); }
                return $res;
            };

            $res = null;
            foreach ($body as $expr) { $res = evaluate($expr, $localEnv); }
            return $res;
    }

    $proc = evaluate($op, $env);
    $args = array_map(fn($x) => evaluate($x, $env), array_slice($ast, 1));

    // CRITICAL FIX: Only execute if it's a registered environment closure.
    // This prevents strings matching native functions (like "current") from executing natively.
    if ($proc instanceof Closure) {
        return call_user_func_array($proc, $args);
    }

    throw new Exception("Unknown operator: " . print_r($op, true));
}

// Global Base Environment
$globalEnv = [
    '+' => fn(...$args) => array_sum($args),
    '-' => fn(...$args) => $args[0] - array_sum(array_slice($args, 1)),
    '*' => fn(...$args) => array_product($args),
    '>' => fn($a, $b) => $a > $b,
    '=' => fn($a, $b) => $a == $b,
    'print' => function($x) { echo $x . "\n"; return null; },
];

// --- 3. SRFI-49 INDENTATION PARSER ---

function parse_i_expressions(string $code): array {
    $lines = explode("\n", $code);
    $blocks = [];
    $indentStack = [0];
    $currentBlock = [];

    foreach ($lines as $line) {
        if (trim($line) === '' || str_starts_with(trim($line), ';')) continue;

        preg_match('/^(\s*)/', $line, $matches);
        $indent = strlen($matches[1]);
        $cleanLine = trim($line);

        preg_match_all('/"[^"]*"|\(|\)|[^\s()]+/', $cleanLine, $matches);
        $lineTokens = $matches[0];

        $parsedLine = [];
        while (!empty($lineTokens)) { $parsedLine[] = build_ast($lineTokens); }

        if ($indent > end($indentStack)) {
            $indentStack[] = $indent;
            $currentBlock[] = ['__INDENT__', $parsedLine];
        } else {
            while ($indent < end($indentStack)) {
                array_pop($indentStack);
                $currentBlock[] = ['__DEDENT__'];
            }
            $currentBlock[] = $parsedLine;
        }
    }
    while (count($indentStack) > 1) { array_pop($indentStack); $currentBlock[] = ['__DEDENT__']; }

    return reconstruct_tree($currentBlock);
}

function build_ast(array &$tokens): mixed {
    $token = array_shift($tokens);
    if ($token === '(') {
        $list = [];
        while (!empty($tokens) && $tokens[0] !== ')') { $list[] = build_ast($tokens); }
        array_shift($tokens);
        return $list;
    }
    return is_numeric($token) ? (float)$token : $token;
}

function reconstruct_tree(array $flatBlocks): array {
    $stack = [[]];
    foreach ($flatBlocks as $node) {
        if (isset($node[0]) && $node[0] === '__INDENT__') {
            $stack[] = $node[1];
        } elseif (isset($node[0]) && $node[0] === '__DEDENT__') {
            $child = array_pop($stack);
            $parent = array_pop($stack);
            $parent[] = $child;
            $stack[] = $parent;
        } else {
            $current = array_pop($stack);
            if (empty($current)) { $current = $node; } else { $current[] = $node; }
            $stack[] = $current;
        }
    }
    return $stack[0];
}

// --- 4. EXECUTION DEMO ---

$schemeCode = <<<SCHEME
define count-up-to max
  let loop ((current 1))
    cond
      > current max
        print "Generator finished"
      else
        yield current
        loop (+ current 1)
SCHEME;

$ast = parse_i_expressions($schemeCode);

// 1. Define the generator function template globally
evaluate($ast[0], $globalEnv);

// 2. Instantiate our generator using the proper 'let' AST structure
$genExpr = [['let', 'loop', [['current', 1]], ['cond', [['>', 'current', 'max'], ['print', '"Generator finished"']], ['else', ['yield', 'current'], ['loop', ['+', 'current', 1]]]]]];
$genEnv = array_merge($globalEnv, ['max' => 3]);
$generator = new SchemeGenerator($genExpr, $genEnv);

// 3. Run the generator loops
print("Result 1: " . $generator->next() . "\n"); 
print("Result 2: " . $generator->next() . "\n"); 
print("Result 3: " . $generator->next() . "\n"); 
print("Result 4: " . $generator->next() . "\n");
