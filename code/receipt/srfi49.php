<?php

class ParseException extends Exception {}

class SRFI49
{

	function parse($code) {
		// 1. NEW: Pre-processor to collapse multiline parentheses
		$rawLines = explode("\n", $code);
		$collapsedLines = [];
		$currentLine = "";
		$openParens = 0;

		foreach ($rawLines as $l) {
			$clean = explode(';', $l)[0]; // Ignore comments
			$openParens += substr_count($clean, '(') - substr_count($clean, ')');

			$currentLine = ($currentLine === "") ? $l : $currentLine . " " . trim($l);

			if ($openParens <= 0) {
				$collapsedLines[] = $currentLine;
				$currentLine = "";
				$openParens = 0; // Reset stray negatives
			}
		}
		if ($currentLine !== "") $collapsedLines[] = $currentLine; // Catch remaining text

		// 2. The original parsing engine (now reading from $collapsedLines)
		$lines = [];
		foreach ($collapsedLines as $l)
			if (preg_match('/^(\s*)([^;\s].*)/', explode(';', $l)[0], $m))
				if (preg_match_all('/[()]|[^\s()]+/', $m[2], $t) && $t[0])
					$lines[] = [strlen($m[1]), $t[0]];
		$i = 0;
		$b = function($ind) use (&$lines, &$i, &$b) {
			$res = [];
			while ($i < count($lines)) {
				list($l, $toks) = $lines[$i];
				if ($ind === null) $ind = $l;
				if ($l < $ind) break;
				$i++; $p = 0;
				$s = function() use (&$toks, &$p, &$s) {
					if (($t = $toks[$p++] ?? '') === '(') {
						for ($r = []; $p < count($toks) && $toks[$p] !== ')';) $r[] = $s();
						if (($toks[$p] ?? '') !== ')') {
							throw new ParseException("Syntax Error: Mismatched parentheses in: '" . implode(' ', $toks) . "'");
						}
						$p++; return $r;
					}
					return is_numeric($t) ? $t+0 : $t;
				};
				for ($h = []; $p < count($toks);) $h[] = $s();
				if (($h[0] ?? '') === 'group') array_shift($h);
				$ch = ($i < count($lines) && $lines[$i][0] > $l) ? $b($lines[$i][0]) : [];
				$res[] = $ch ? ($h ? array_merge($h, $ch) : $ch) : (count($h) == 1 ? $h[0] : $h);
			}
			return $res;
		};
		return $b(null);
	}

	function eval($e, &$env = []) {
		if (!is_array($e)) return is_numeric($e) ? $e+0 : ($env[$e] ?? $e);
		$op = $this->eval($e[0], $env);
		if ($op === 'define') {
			if (is_array($e[1])) {
				return $env[$e[1][0]] = function(...$args) use ($e, &$env) { 
					$m = array_combine(array_slice($e[1], 1), $args) + $env;
					return $this->eval($e[2], $m); 
				};
			}
			return $env[$e[1]] = $this->eval($e[2], $env);
		}
		// 1. Updated 'if' to make the else branch optional
		if ($op === 'if') {
			$cond = $this->eval($e[1], $env);

			$thenBranch = [];
			$elseBranch = [];
			$foundElse = false;

			// Everything before 'else' goes to thenBranch, everything after goes to elseBranch
			foreach (array_slice($e, 2) as $stmt) {
				if ($stmt === 'else') {
					$foundElse = true;
					continue;
				}
				if (is_array($stmt) && ($stmt[0] ?? '') === 'else') {
					$foundElse = true;
					$elseBranch = array_merge($elseBranch, array_slice($stmt, 1));
					continue;
				}

				if (!$foundElse) {
					$thenBranch[] = $stmt;
				} else {
					$elseBranch[] = $stmt;
				}
			}

			// Execute the matching branch sequentially
			$body = $cond ? $thenBranch : $elseBranch;
			$res = null;
			foreach ($body as $stmt) {
				$res = $this->eval($stmt, $env);
			}
			return $res;
		}

		// 2. Added 'begin' block handler to execute multiple statements sequentially
		if ($op === 'begin') {
			$res = null;
			foreach (array_slice($e, 1) as $x) $res = $this->eval($x, $env);
			return $res;
		}

		$args = [];
		foreach (array_slice($e, 1) as $x) $args[] = $this->eval($x, $env);
		if (is_callable($op)) return $op(...$args);

		// 3. Added 'print' to the core primitives for testing side-effects
		return match($op) { 
		'+' => array_sum($args), 
			'*' => array_product($args), 
			'-' => $args[0] - ($args[1] ?? 0), 
			'=' => $args[0] == $args[1], 
			'print' => print($args[0] . "\n"),
			default => $op 
		};
	}
}

$engine = new SRFI49();

$code = "
define (fac x)
  if (= x 0)
	1
	* x
	  fac (- x 1)

(fac 4)
";

$code2 = <<<LISP
(if 1 
    (begin 
        (define a 20)
        (* a 2.5)))

if 0 
  define a 10
  * a 2.5
  else
    111
LISP;

// Multiline parenthesis does NOT work, only one-liner

$ast = $engine->parse($code2);

$env = [];
$result = null;
foreach ($ast as $expr) {
	$result = $engine->eval($expr, $env);
}

echo "Result: " . $result . PHP_EOL; // Outputs: Result: 120
