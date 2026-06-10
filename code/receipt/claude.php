<?php
/**
 * SRFI-49 I-expression receipt template engine.  PHP 7.4+
 *
 * Template DSL features:
 *   if cond then else    — conditional
 *   each items var body  — loop over array
 *   + - * /             — arithmetic
 *   = < > <= >=         — comparisons
 *   not and or          — boolean logic
 *   str v1 v2 …         — concatenate
 *   pad str width [ch]  — left-pad  (e.g. pad price 8 " ")
 *   rpad str width [ch] — right-pad
 *   fmt-money n         — "1,234.56"
 *   fmt-num n dec       — fixed decimals
 *   upper / lower       — case
 *   repeat ch n         — "--------"
 *   get var.path.key    — dotted lookup into data
 *   node name …attrs    — emits a renderer node
 *
 * Usage:
 *   $tpl = new ReceiptTemplate(new HtmlRenderer);
 *   echo $tpl->render($template, $data);
 */

// ── Renderer contract ─────────────────────────────────────────────────────────
interface ReceiptRenderer {
    /** Column width of the target medium (characters / cells). */
    public function width(): int;
    /** Called for every `node` form. $attrs is [key=>value, …]. */
    /** @return mixed */
    public function node(string $name, array $attrs, array $children);
    /** Called once with the root results to produce final output. */
    public function finalize(array $nodes): string;
}

// ── Bundled renderers ─────────────────────────────────────────────────────────

/** 40-column thermal receipt printer. */
class TextRenderer implements ReceiptRenderer {
    private $cols;
    public function __construct(int $cols = 40) { $this->cols = $cols; }
    public function width(): int { return $this->cols; }
    public function node(string $name, array $attrs, array $children) {
        $w = $this->cols;
        if ($name === 'line')    return ($attrs['text'] ?? implode('', $attrs))."\n";
        if ($name === 'hr')      return str_repeat($attrs['char'] ?? '-', $w)."\n";
        if ($name === 'section') return implode('', $children);
        if ($name === 'col2')    return sprintf("%-".($w - strlen((string)$attrs['right']))."s%s\n",
                                                $attrs['left'], $attrs['right']);
        return implode('', $children)."\n";
    }
    public function finalize(array $nodes): string { return implode('', $nodes); }
}

/** HTML — width is a CSS max-width hint in ch units, layout via flexbox. */
class HtmlRenderer implements ReceiptRenderer {
    private $cols;
    public function __construct(int $cols = 48) { $this->cols = $cols; }
    public function width(): int { return $this->cols; }
    public function node(string $name, array $attrs, array $children) {
        $inner = implode('', array_map(function($c) { return is_string($c) ? htmlspecialchars($c) : (string)$c; }, $children));
        if ($name === 'hr')      return "<hr>\n";
        if ($name === 'line')    return "<p>".htmlspecialchars($attrs['text'] ?? implode('', $attrs))."</p>\n";
        if ($name === 'col2')    return "<div class=\"col2\"><span>".htmlspecialchars($attrs['left'])."</span>"
                                       ."<span>".htmlspecialchars($attrs['right'])."</span></div>\n";
        if ($name === 'section') return "<section>$inner</section>\n";
        return "<div class=\"$name\">$inner</div>\n";
    }
    public function finalize(array $nodes): string {
        $w = $this->cols;
        return "<div class=\"receipt\" style=\"max-width:{$w}ch\">\n".implode('', $nodes)."</div>\n";
    }
}

/** JSON — width stored as metadata, useful for downstream renderers. */
class JsonRenderer implements ReceiptRenderer {
    private $cols;
    public function __construct(int $cols = 0) { $this->cols = $cols; } // 0 = no constraint
    public function width(): int { return $this->cols; }
    public function node(string $name, array $attrs, array $children) {
        return ['node' => $name, 'attrs' => $attrs, 'children' => $children];
    }
    public function finalize(array $nodes): string {
        return json_encode(['width' => $this->cols, 'nodes' => $nodes], JSON_PRETTY_PRINT);
    }
}

// ── Template engine ───────────────────────────────────────────────────────────
class ReceiptTemplate
{
    /** @var ReceiptRenderer */
    private $renderer;
    public function __construct(ReceiptRenderer $renderer) { $this->renderer = $renderer; }

    public function render(string $src, array $data): string {
        // Inject renderer width as a first-class template variable.
        $data['_width'] = $this->renderer->width();

        $lines   = $this->lex($src);
        $pos     = 0;
        $results = [];
        while ($pos < count($lines)) {
            [$ast, $pos] = $this->parse($lines, $pos);
            $results[] = $this->eval($ast, $data);
        }
        return $this->renderer->finalize(array_filter($results, function($v) { return $v !== null; }));
    }

    // ── Lexer ─────────────────────────────────────────────────────────────────
    private function lex(string $src): array {
        $out = [];
        foreach (explode("\n", $src) as $raw) {
            preg_match('/^([ \t]*)(.*)$/', $raw, $m);
            $body = trim($m[2]);
            if ($body === '' || $body[0] === ';') continue;
            $out[] = ['i' => $m[1], 't' => $this->scan($body)];
        }
        return $out;
    }

    private function scan(string $s): array {
        // Match: quoted strings | balanced parens (up to 2 levels) | bare tokens
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|\((?:[^()]|\((?:[^()])*\))*\)|[^\s"()]+/', $s, $m);
        return $m[0];
    }

    // ── Parser ────────────────────────────────────────────────────────────────
    /** Returns [ast_node, next_pos]. Never evaluates — just builds the tree. */
    private function parse(array $lines, int $pos): array {
        $tokens = $lines[$pos]['t'];
        $tpos   = 0;
        $head   = [];
        while ($tpos < count($tokens))
            $head[] = $this->atom($tokens, $tpos);

        $pos++;
        $children = [];
        while ($pos < count($lines) && $this->isChild($lines, $pos - 1, $pos)) {
            $childIndent = $lines[$pos]['i'];
            while ($pos < count($lines) && $lines[$pos]['i'] === $childIndent) {
                [$child, $pos] = $this->parse($lines, $pos);
                $children[] = $child;
            }
            break; // one indent level per block
        }

        return [count($children) ? array_merge($head, $children) : $head, $pos];
    }

    private function isChild(array $lines, int $p, int $c): bool {
        $pi = $lines[$p]['i'];
        $ci = $lines[$c]['i'];
        if (strlen($ci) <= strlen($pi)) return false;
        if ($pi === '') return true;
        return strpos($ci, $pi) === 0;
    }

    private function atom(array $t, int &$p) {
        $v = $t[$p++];
        if ($v[0] === '(') {
            // Parenthesised sub-expression captured as one token — parse its interior.
            $inner  = $this->scan(substr($v, 1, -1));
            $ipos   = 0;
            $items  = [];
            while ($ipos < count($inner)) $items[] = $this->atom($inner, $ipos);
            return $items;
        }
        if ($v[0] === '"') return stripslashes(substr($v, 1, -1));
        if (is_numeric($v)) return $v + 0;
        if ($v === '#t' || $v === 'true')  return true;
        if ($v === '#f' || $v === 'false') return false;
        return $v; // symbol
    }

    // ── Evaluator ─────────────────────────────────────────────────────────────
    private function eval(array $expr, array $data) {
        // `each` must intercept before symbol resolution so children stay as raw AST.
        if ($expr[0] === 'each') {
            $listVar  = $expr[1];               // symbol naming the list in $data
            $itemVar  = $expr[2];               // symbol to bind each item to
            $items    = isset($data[$listVar]) ? $data[$listVar] : [];
            $children = array_slice($expr, 3);  // raw unevaluated child ASTs
            $out = [];
            foreach ((array)$items as $item) {
                $itemData = is_array($item)
                    ? array_merge($data, $item, [$itemVar => $item])
                    : array_merge($data, [$itemVar => $item]);
                foreach ($children as $child) {
                    $out[] = $this->eval((array)$child, $itemData);
                }
            }
            return implode('', $out);
        }

        // Resolve symbols and recursively evaluate sub-expressions.
        $expr = array_map(function($x) use ($data) {
            if (is_array($x))  return $this->eval($x, $data);
            if (is_string($x)) return isset($data[$x]) ? $data[$x] : $x;
            return $x;
        }, $expr);

        $op   = $expr[0];
        $args = array_slice($expr, 1);

        if ($op === 'if') {
            return isset($args[(bool)$args[0] ? 1 : 2]) ? $args[(bool)$args[0] ? 1 : 2] : null;
        }

        // Built-ins; unknown ops fall through to the renderer (line, hr, col2, section, …)
        if ($op==='+')         return array_sum($args);
        if ($op==='-')         return count($args)===1 ? -$args[0] : $args[0] - array_sum(array_slice($args,1));
        if ($op==='*')         return array_product($args);
        if ($op==='/')         return array_reduce(array_slice($args,1), function($c,$x){ return $c/$x; }, $args[0]);
        if ($op==='=')         return $args[0] == $args[1];
        if ($op==='<')         return $args[0] <  $args[1];
        if ($op==='>')         return $args[0] >  $args[1];
        if ($op==='<=')        return $args[0] <= $args[1];
        if ($op==='>=')        return $args[0] >= $args[1];
        if ($op==='not')       return !$args[0];
        if ($op==='and')       return (bool)array_product(array_map('boolval', $args));
        if ($op==='or')        return (bool)array_sum(array_map('boolval', $args));
        if ($op==='str')       return implode('', $args);
        if ($op==='pad')       return str_pad((string)$args[0], (int)$args[1], isset($args[2]) ? $args[2] : '0', STR_PAD_LEFT);
        if ($op==='rpad')      return str_pad((string)$args[0], (int)$args[1], isset($args[2]) ? $args[2] : '0', STR_PAD_RIGHT);
        if ($op==='fmt-money') return number_format((float)$args[0], 2);
        if ($op==='fmt-num')   return number_format((float)$args[0], (int)(isset($args[1]) ? $args[1] : 2));
        if ($op==='upper')     return strtoupper((string)$args[0]);
        if ($op==='lower')     return strtolower((string)$args[0]);
        if ($op==='repeat')    return str_repeat((string)$args[0], (int)$args[1]);
        if ($op==='get')       return $this->dotGet($data, (string)$args[0]);
        return $this->renderer->node(
            (string)$op,
            $this->attrsFrom($args),
            array_values(array_filter($args, 'is_array'))
        );
    }

    /** Dotted path lookup: get order.total  →  $data['order']['total'] */
    private function dotGet(array $data, string $path) {
        foreach (explode('.', $path) as $k)
            $data = is_array($data) ? ($data[$k] ?? null) : null;
        return $data;
    }

    /** Build a key=>value attrs map: odd-positioned scalars are keys, the following value is anything. */
    private function attrsFrom(array $args): array {
        $args  = array_values($args);
        $attrs = [];
        $i     = 0;
        while ($i < count($args)) {
            if (!is_array($args[$i]) && isset($args[$i + 1])) {
                $attrs[$args[$i]] = $args[$i + 1];
                $i += 2;
            } else {
                $i++;
            }
        }
        return $attrs;
    }
}

// ReceiptTemplateEngine is now just an alias — each is handled inside eval().
class ReceiptTemplateEngine extends ReceiptTemplate {}

// ── Demo ──────────────────────────────────────────────────────────────────────
$data = [
    'store'    => 'ACME STORE',
    'address'  => '123 Main St',
    'cashier'  => 'Jane',
    'items'    => [
        ['name'=>'Widget A',  'qty'=>2,  'price'=>4.99],
        ['name'=>'Gadget B',  'qty'=>1,  'price'=>12.50],
        ['name'=>'Doohickey', 'qty'=>3,  'price'=>1.75],
    ],
    'subtotal' => 29.23,
    'tax_rate' => 0.08,
    'paid'     => 35.00,
];
$data['tax']   = round($data['subtotal'] * $data['tax_rate'], 2);
$data['total'] = round($data['subtotal'] + $data['tax'], 2);
$data['change']= round($data['paid'] - $data['total'], 2);

$template = <<<'TPL'
section
  line text (upper store)
  line text address
  hr char -
  line text (str "Cashier: " cashier)
hr char =
each items item
  col2 left (get item.name) right (str (get item.qty) " x " (fmt-money (get item.price)))
hr char -
col2 left "Subtotal" right (fmt-money subtotal)
col2 left "Tax (8%)" right (fmt-money tax)
hr char =
col2 left "TOTAL"    right (fmt-money total)
hr char -
col2 left "Cash"     right (fmt-money paid)
col2 left "Change"   right (fmt-money change)
hr char =
TPL;

foreach (['Text/40'=>new TextRenderer(40), 'Text/32'=>new TextRenderer(32), 'HTML'=>new HtmlRenderer(48), 'JSON'=>new JsonRenderer(0)] as $label=>$r) {
    echo "\n===== $label =====\n";
    $engine = new ReceiptTemplateEngine($r);
    echo $engine->render($template, $data);
}
