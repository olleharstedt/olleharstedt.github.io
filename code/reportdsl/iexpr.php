<?php

$sc = <<<SCHEME
+ 1 1
SCHEME;

$sc2 = <<<SCHEME
+
  * 2 3
  1
SCHEME;

class UnknownOperationException extends RuntimeException {}

abstract class SexprBase
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
            var_dump($line);
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

            if ($currentIndent > $previousIndent) {
                echo 'push' . PHP_EOL;
                $prev = $current;
                $history->push($current);
                $current = new SplStack();
                $prev->push($current);
            } else {
                var_dump($currentIndent);
                var_dump($previousIndent);
                for ($i = 0; $i < ($currentIndent - $previousIndent); $i += 2) {
                    /*
                    if ($buffer) {
                        $current->push($buffer);
                        $buffer = '';
                    }
                     */
                    //$current = $history->pop();
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
            case '*':
                $arg1 = $sexpr->shift();
                $arg2 = $sexpr->shift();
                return $this->mathEval($arg1) * $this->mathEval($arg2);
            default:
                throw new UnknownOperationException($op);
        }
    }
}

$report = new MathSexpr();
$sexp = $report->parse($sc2);
print_r($sexp);
//echo $report->mathEval($sexp);
echo "\n";
