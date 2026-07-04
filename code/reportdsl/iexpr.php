<?php

$sc = <<<SCHEME
+ 1 1
SCHEME;

// + 1 1            (+ 1 1)
// + 1              (+ 1
//   1                (1))

$sc2 = <<<SCHEME
+
  * 2 3
  + 4 5
SCHEME;

class UnknownOperationException extends RuntimeException {}

abstract class _SexprBase
{
    /**
     * @return SplStack<string>
     */
    public function parse(string $sc)
    {
        // Remove comments
        $sc = preg_replace('/;.*$/m', '', $sc);

        // Explode on newline
        $lines = explode(PHP_EOL, $sc);

        // Normalize string
        //$sc = trim((string) preg_replace('/[\t\n\r\s]+/', ' ', $sc));

        $current = new SplStack();
        $base = $current;
        $prev = null;
        $history = new SplStack();
        $buffer = '';
        $previousIndent = -1;

        foreach ($lines as $line) {
            $inside_quote = 0;
            $currentIndent = 0;
            for ($i = 0; $i < strlen($line); $i++) {
                $char = $line[$i];
                if ($char == ' ') {
                    $currentIndent++;
                } else {
                    break;
                }
            }
            $line = trim($line);

            for ($i = 0; $i < strlen($line); $i++) {
                $char = $line[$i];
                if ($char === '"') {
                    $inside_quote = 1 - $inside_quote;
                } elseif ($char === ' ' && !$inside_quote) {
                    if ($buffer !== '') {
                        $current->push($buffer);
                        $buffer = '';
                    }
                } else {
                    $buffer .= $char;
                }
                /*
                 */
            } 
            if ($buffer) {
                $current->push($buffer);
                $buffer = '';
            }

            printf("%d %d\n", $currentIndent, $previousIndent);
            if ($currentIndent > $previousIndent) {
                $prev = $current;
                $history->push($current);
                $current = new SplStack();
                $prev->push($current);
            } else {
                for ($i = 0; $i <= ($currentIndent - $previousIndent); $i += 2) {
                    if ($buffer) {
                        $current->push($buffer);
                        $buffer = '';
                    }
                    $current = $history->pop();
                }
            }
            $previousIndent = $currentIndent;
        }
        if ($buffer) {
            $current->push($buffer);
            $buffer = '';
        }
        $current = $history->pop();

        return $base;
    }
}

abstract class SexprBase
{
    /**
     * Parses an I-expression string into a nested PHP array structure.
     */
    public function parse(string $sc): array
    {
        // 1. Clean up comments and unify line endings
        $sc = preg_replace('/;.*$/m', '', $sc);
        $sc = str_replace(["\r\n", "\r"], "\n", $sc);
        $lines = explode("\n", $sc);

        $root = [];
        
        // This stack will store pairs of [indentation_level, reference_to_array_node]
        $indentStack = [
            [-1, &$root]
        ];

        foreach ($lines as $line) {
            // Skip completely empty or pure whitespace lines
            if (trim($line) === '') {
                continue;
            }

            // 2. Determine indentation level
            $currentIndent = 0;
            for ($i = 0; $i < strlen($line); $i++) {
                if ($line[$i] === ' ') {
                    $currentIndent++;
                } else {
                    break;
                }
            }

            // 3. Tokenize the line content (respecting quotes)
            $tokens = $this->tokenizeLine(trim($line));
            if (empty($tokens)) {
                continue;
            }

            // 4. Adjust the stack according to indentation
            // Pop off the stack until we find the parent level we belong to
            while (end($indentStack)[0] >= $currentIndent) {
                array_pop($indentStack);
            }

            // Get a reference to the active parent array layer
            $currentParent = &$indentStack[count($indentStack) - 1][1];

            // 5. Create a new sub-list block for this line's content
            $newLineBlock = $tokens;
            
            // Append it to the parent layer
            $currentParent[] = &$newLineBlock;

            // Push this new block onto our indentation stack so subsequent 
            // deeper indented lines can nest inside it
            $indentStack[] = [$currentIndent, &$newLineBlock];
            
            unset($newLineBlock);
            unset($currentParent);
        }

        return $root;
    }

    /**
     * Splits a single line into string tokens, preserving quoted text.
     */
    private function tokenizeLine(string $line): array
    {
        $tokens = [];
        $buffer = '';
        $insideQuote = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if ($char === '"') {
                $insideQuote = !$insideQuote;
                $buffer .= $char; // Keep quotes, or omit if you prefer raw text
            } elseif ($char === ' ' && !$insideQuote) {
                if ($buffer !== '') {
                    $tokens[] = $buffer;
                    $buffer = '';
                }
            } else {
                $buffer .= $char;
            }
        }
        if ($buffer !== '') {
            $tokens[] = $buffer;
        }

        return $tokens;
    }
}

class MathSexpr extends SexprBase
{
    /**
     * Evaluates a nested array S-expression or an atom.
     * 
     * @param array|string $sexpr
     */
    public function mathEval($sexpr): int
    {
        // 1. Base case: If it's a raw string token, it's a number. Turn it into an int.
        if (is_string($sexpr)) {
            return intval($sexpr);
        }

        // 2. Structural safety: If it's empty, it evaluates to 0 (or throw an exception).
        if (empty($sexpr)) {
            return 0;
        }

        // 3. Root wrapper handling: If the first element is *another array*, 
        // it means we were handed the top-level program list. Evaluate the first statement.
        if (is_array($sexpr[0])) {
            return $this->mathEval($sexpr[0]);
        }

        // 4. Lisp-style evaluation: The first element of the array is the operator.
        // We copy the array so we don't accidentally mutate the original data structure.
        $expr = $sexpr;
        $op = array_shift($expr);

        switch ($op) {
            case '+':
                $arg1 = array_shift($expr);
                $arg2 = array_shift($expr);
                return $this->mathEval($arg1) + $this->mathEval($arg2);
                
            case '-':
                $arg1 = array_shift($expr);
                $arg2 = array_shift($expr);
                return $this->mathEval($arg1) - $this->mathEval($arg2);
                
            case '*':
                $arg1 = array_shift($expr);
                $arg2 = array_shift($expr);
                return $this->mathEval($arg1) * $this->mathEval($arg2);
                
            default:
                throw new Exception("Unknown operation: " . (is_string($op) ? $op : 'Array'));
        }
    }
}

$report = new MathSexpr();
$sexp = $report->parse($sc2);
//print_r($sexp);
echo $report->mathEval($sexp);
echo "\n";
