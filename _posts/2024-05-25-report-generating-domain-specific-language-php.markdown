---
layout: post
title:  Report generating domain-specific language
subtitle: Forth-like and S-expression with PHP
date:   2024-05-25
categories: programming php dsl
---

Why can't the customers write their own damn reports? Or the sales people. Or the technical support. They already know some basic SQL, right? How hard could it be to create a safe subset of SQL, that also includes some HTML formatting capability? But the parser would have to be dirt simple. I don't want to end up with a big pile of code only I can maintain. What kind of language would work best? S-expressions? Forth? JSON? It would have to be able to deal with complex calculations between database columns, joins, perhaps some summarizing calculations after the fetch. And CSS information, too.

## Intro

A [domain-specific language](https://en.wikipedia.org/wiki/Domain-specific_language) (DSL) is a pretty neat **design pattern**, written extensively about by Martin Fowler in his [book](https://www.martinfowler.com/dslCatalog/).

For my use-case, writing a text-based report generator, there are some pros and cons.

**Pros**:

* You can add new reports without waiting for a new software release
* Semi-technical people can make changes to the DSL scripts
* Each customer can have their own custom-made reports
* You gather all related data and logic in one place instead of spreading it out between SQL, PHP, CSS, JavaScript
* Sandboxed and safe

**Cons**:

* [Inner-platform effect](https://en.wikipedia.org/wiki/Inner-platform_effect)
* Might only be understood by me, in the end
* The flexibility that the DSL allows might not be needed in the end (e.g. all customers actually need the same reports)

The formats I considered simple enough to try out:

* [S-expressions](https://en.wikipedia.org/wiki/S-expression), because it can be lexed and parsed in a handful of lines
* [Forth](https://en.wikipedia.org/wiki/Forth_(programming_language))-like, for the same reason
* JSON, because it's common in web and fairly easy to read (with some syntax highlight)

The DSL should be able to seamlessly blend:

* Structured data for HTML, CSS, SQL
* Logic in SQL, PHP and possibly JavaScript

Use-case: An article report

* Includes calculation of margin of profit for each article
* Includes totals
* Includes labels for the different columns

## Trying out the DSL design

Originally, fetching data from a database, calculating some totals and formatting the result, would include logic in three different languages - PHP, SQL, and HTML [^1]. Something like:

```php
$sql = <<<SQL
SELECT
    `article_id`,
    round((100 * (1 - (`purchase_price` / `selling_price`))), 2) AS margin
FROM `articles`";
SQL;
$data = execute_query($sql);
$totals = calculate_totals($data);    // Loops data with PHP
echo generate_report($data, $totals); // HTML template including labels and styling
```

This logic will have to be rewritten for each new report, and each new export type.

### S-expression

S-expressions is a way to express list-based tree-structures, both for logic and data, in a pre-fix manner. Most basic example:

```scheme
(+ 1 2)
```

For the DSL, we'll define our own keywords. Most of it is key-value pairs.

```scheme
(report
    (title "Article report")
    (table "articles")
    (columns
        (column
            (title "Article number")
            (select "article_id")
        )
        (column
            (title "Margin of profit")
            (css "right-align")
            (select (round (* 100 (- 1 (/ purchase_price selling_price))) 2))
            (as "margin")
        )
    )
    (totals
        (total
            (for "margin")
            (do (/ (sum "margin") (count rows)))
        )
    )
)
```

### Forth-like

**Ultra-short introduction to Forth**

* Forth uses as implicit **stack**.
* Forth uses **words** instead of functions
* Words are space-separated, and can consist of any characters, like `2>` or `+` or `if`
* Words can consume the content of the stack
* Words can push results on top of the stack
* Words are collected in **dictionaries**
* The currently active dictionary can be swapped
* **Numbers** are put on top of the stack when evaluated
* Everything that's not a number is executed immediately
* Comments are inside parenthesis, and line-comments are after backslash

Example to add two numbers and show the result:

<div class="highlight">
<pre class="highlight">
<code><span class="mi">1</span>   <span class="c1">\ Put number 1 on top of stack</span>
<span class="mi">2</span>   <span class="c1">\ Put number 2 on top of stack, pushing number 1 down</span>
<span class="o">+</span>   <span class="c1">\ This is a word! It adds the two top stack elements and pushes the result on the stack</span>
.   <span class="c1">\ The dot-word shows the content of the stack, in this case "3"</span></code>
</pre>
</div>

This can also be written as:

```
1 2 + .
```

**Variables in Forth**

The word `variable` takes the next word in the input stream, and creates a space in memory for it.

Example:

<div class="highlight">
<pre class="highlight">
<code><span class="k">variable</span> a   <span class="c1">\ Create variable a</span></code>
</pre>
</div>

The words `!` and `@` are used to store and fetch content from variables.

Example:

<div class="highlight">
<pre class="highlight">
<code><span class="k">variable</span> a   <span class="c1">\ Create variable a</span>
<span class="mi">10</span> a <span class="o">!</span>       <span class="c1">\ Push 10 on top of stack, and store it in variable a</span>
a <span class="o">@</span>          <span class="c1">\ Fetch content of variable a and push it on top of stack</span>
.            <span class="c1">\ Show top of stack, in this case "10"</span></code>
</pre>
</div>

With this very short introduction, it should be possible to understand a Forth-like report generating DSL (same use-case as the S-expression above):

<div class="highlight">
<pre class="highlight">
<code><span class="k">var</span> report          <span class="c1">\ Create variable report</span>
<span class="k">new</span> table report !  <span class="c1">\ Save table data structure to new variable</span>
report @ <span class="s">"Article report"</span> set title   <span class="c1">\ report.title = "Article report"</span>
report @ <span class="s">"articles"</span> set table

<span class="k">var</span> columns         <span class="c1">\ New variable for report columns</span>
<span class="k">new</span> list columns !  <span class="c1">\ Create list</span>

<span class="k">var</span> column          <span class="c1">\ Each column is a table of data</span>
<span class="k">new</span> table column !
column @ <span class="s">"Artnr"</span> set title
column @ <span class="s">"article_id"</span> set select
columns @ column @ push     <span class="c1">\ Push column to columns list</span>

<span class="k">new</span> table column !
column @ <span class="s">"Diff"</span> set title
column @ <span class="s">"diff"</span> set as
column @ set-sql    <span class="c1">\ Switching to the SQL dictionary</span>
    <span class="mi">100</span> <span class="mi">1</span> <span class="s">"purchase_price"</span> <span class="s">"selling_price"</span> / - * <span class="mi">2</span> round
end-sql set select  <span class="c1">\ Switching back to main dictionary</span>
columns @ column @ push
report @ columns @ set columns      <span class="c1">\ Store all columns in the report table</span>

<span class="k">var</span> rows
run-query rows !    <span class="c1">\ "run-query" will construct the query and run it, populating the rows variable</span>
report @ rows @ set rows

<span class="k">var</span> totals
<span class="k">new</span> list totals !

<span class="k">var</span> total
<span class="k">new</span> table total !
total @ <span class="s">"diff"</span> set for
total @ set-php     <span class="c1">\ Switching to the PHP dictionary</span>
    rows @ sum diff 
    count rows
    /
end-php set result
totals @ total @ push
report @ totals @ set totals
</code>
</pre>
</div>

The translation from SQL to Forth-like post-fix notation looks quite awkward, but it can be improved with some custom words, see below.

### JSON

```json
{
    "title": "Article report",
    "table": "articles",
    "columns": [
        {
            "title": "Article number",
            "select": "article_id"
        },
        {
            "title": "Margin",
            "select": {
                "op": "round",
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
        },
    ]
}
```

You can squeeze the SQL expression a little more, for example:

```json
"select": [
    "round", [[ "*", [100, ["-", [1, ["/", ["purchase_price", "selling_price"]]]]]], 2]
]
```

The JSON format works excellent for structured data, as it was designed to do, but does not scale well for logical expressions (unless you want to write expressions as a string, in which case you need another lexer/parser anyway, or give up on any security or sandboxing).

## Parse S-expression in PHP

Parsing an S-expression is easy enough in any language - use a tree of stacks and push/pop words and new stacks on the stacks, depending on when you encounter ')' or '(' or space characters.

The parser also has an `$inside_quote` state to be able to deal with quoted strings.

```php
function parse(string $sc)
{
    // Normalize string
    $sc = trim((string) preg_replace('/[\t\n\r\s]+/', ' ', $sc));
    $current = new SplStack();
    $history = new SplStack();
    $base = $current;
    $prev = null;
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
```

## Parse Forth-like in PHP

Parsing a Forth-like stream of words is slightly different than parsing S-expressions.

First, words ("functions") in Forth are executed as soon as they are read, so lexing/parsing happens at the same time as execution of the script (!).

The Forth-like implementation needs a string buffer that lets us get the next word in the stream:

```php
class StringBuffer
{
    /** @var string */
    private $buffer;

    /** @var int */
    private $pos = 0;

    private $inside_quote = 0;

    public function __construct(string $s)
    {
        // Remove comments
        $s = preg_replace('/\\\.*$/m', '', $s);
        // Normalize string
        $s = trim((string) preg_replace('/[\t\n\r\s]+/', ' ', $s));
        // Two extra spaces for the while-loop to work
        $this->buffer = $s. '  ';
    }

    /**
     * Get the next word in the buffer.
     */
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
```

## Parse JSON in PHP

I'll leave out JSON, since PHP already has a `json_decode` function.

## Constructing a SQL query from the DSL

Since S-expressions can be used for both structured data and logic, we can use it to mix SQL snippets in the data, as done to calculate the profit margin of a product:

```scheme
(select (round (* 100 (- 1 (/ purchase_price selling_price))) 2))
```

To build a SQL string from an S-expression, it's a basic recursive evaluation:

```php
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
```

The Forth-like DSL is a bit different, since each word has access to the string stream.

The eval loop assumes a chain of dictionaries, and a string buffer to loop on. The resulting stack is returned.

```php
function eval_buffer(StringBuffer $buffer, Dicts $dict): SplStack
{
    $stack  = new SplStack();
    while ($word = $buffer->next()) {
        $fn = $dicts->getWord($word);
        if (trim($word, '"') !== $word) {
            $stack->push($word);
            // Digit
        } elseif (ctype_digit($word)) {
            $stack->push($word);
            // Execute dict word
        } elseif ($fn) {
            $fn($stack, $buffer, $word);
        } else {
            throw new RuntimeException('Word is not a string, not a number, and not in dictionary: ' . $word);
        }
    }
    return $stack;
}
```

An example dictionary for a math DSL:

```php
$mathDict = new Dict();
// Add the plus word
$mathDict->addWord('+', function($stack, $buffer, $word) {
    // The plus word pops the two words from the stack
    $a = $stack->pop();
    $b = $stack->pop();
    // Push the result on the stack
    $stack->push($a + $b);
});
// Add the dot word
$mathDict->addWord('.', function ($stack, $buffer, $word) use ($mainDict) {
    $a = $stack->pop();
    echo $a;
});
$stack = eval_buffer(new StringBuffer('1 2 + .'), [$mathDict]);
```

In the case of a SQL SELECT statement, we need words to build a string instead.

```php
$sqlDict = new Dict();
$sqlDict->addWord('/', function($stack, $buffer, $word) {
    $b = $stack->pop();
    $a = $stack->pop();
    $stack->push('(' . $a . ' / ' . $b . ')');
});
$sqlDict->addWord('-', function($stack, $buffer, $word) {
    $b = $stack->pop();
    $a = $stack->pop();
    $stack->push('(' . $a . ' - ' . $b . ')');
});
$sqlDict->addWord('*', function($stack, $buffer, $word) {
    $b = $stack->pop();
    $a = $stack->pop();
    $stack->push('(' . $a . ' * ' . $b . ')');
});
$sqlDict->addWord('round', function($stack, $buffer, $word) {
    $b = $stack->pop();
    $a = $stack->pop();
    $stack->push('round(' . $a . ', ' . $b . ')');
});
```

Using this, you get

```php
$stack = eval_buffer(new StringBuffer('100 1 "purchase_price" "selling_price" / - * 2 round'), $sqlDict);
// Shows "round((100 * (1 - ("purchase_price" / "selling_price"))), 2)"
echo $stack->pop();
```

The main problem here being that `100 1 "purchase_price" "selling_price" / - * 2 round` is utterly unreadable for anyone. Translating from SQL to this formatting is also pretty challenging, regardless if it's easy to read or not.

You can use the same technique to build up HTML, calculate totals fetched from database, and other things.

The full demo code for S-expression DSL can be found [here](https://github.com/olleharstedt/olleharstedt.github.io/blob/master/code/reportdsl/sexpr.php), and the Forth-like can be found [here](https://github.com/olleharstedt/olleharstedt.github.io/blob/master/code/reportdsl/forthlike2.php).

## Questions

> Why not just use a syntax that they're familiar with?

1. For office political reasons, I want the lexer/parser to be simple; intuitive syntax often requires complicated lexer and parser systems
2. The DSL might include SQL, PHP and JavaScript, in which case the syntax has to be different anyway

> From my experience of working with non-programmers, they are usually better off (and happier) using a GUI.

That would be really cool, but I lack the resources to do a proper report generation interface, and the mega-enterprise third-party softwares available does not hook into our framework very well.

## Previous work

There are lots of DSLs out there, but I found nothing for PHP and report generation.

There is one report DSL for Python that is not actively maintained anymore: [glowing-chainsaw](https://github.com/kjosib/glowing-chainsaw).

---

[^1]: Note that an ORM will not work here - for report data you need custom written SQL, anything else would be too slow.
