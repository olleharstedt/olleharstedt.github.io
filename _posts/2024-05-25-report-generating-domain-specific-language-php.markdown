---
layout: post
title:  Report generating domain-specific language
date:   2024-05-25
categories: programming php
---

Why can't the customers write their own damn reports? Or the sales people. Or the technical support. They already know some basic SQL, right? How hard could it be to create a safe subset of SQL, that also includes some HTML formatting capability? But the parser would have to be dirt simple. I don't want to end up with a big pile of code only I can maintain. What kind of language would work best? S-expressions? Forth? JSON? It would have to be able to deal with complex calculations between database columns, joins, perhaps some summarizing calculations after the fetch. And CSS information, too.

## Intro

A domain-specific language is a pretty neat **design pattern**, written extensively about by Martin Fowler [link].

Pros and cons.

Pros:

* You gather all related data and logic in one script
* Semi-technical people can make changes, with some guidance
* You can update reports without waiting for a new software release
* Each customer can have their own custom-made reports

Cons:

* Inner-system trap TODO: Link
* Might only be understood by me, in the end
* The flexibility the DSL allows might not be needed in the end (e.g. all customers actually need the same reports)

The formats I considered:

* S-expressions, because it can be lexed and parsed in a handful of lines
* Forth-like, for the same reason
* JSON, because it's common in web

There are no good lexer/parser libs to PHP that are actively maintained, sadly.

To be able to sell the solution to colleagues, the base system would have to be really simple.

The end result DSL should be able to blend, seamlessly:

* Structured data for HTML, CSS, SQL
* Logic in SQL, PHP and possibly JavaScript

Use-case:

* An article report
* Includes calculation of margin of profit for each article
* Includes totals
* Includes extra options added by JavaScript for this report only

## Trying out the DSL

An outline of how the different alternatives would look like:

**S-expression**

Looks like a primitive version of Lisp, or Lisp without the macros.

```scheme
(report
    (title "Stock report")
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
)
```

**Forth-like**

Forth is a type of post-fix notation, like `1 2 +` equals `3`, but you can make the words (functions, in other languages) eat the next word from the stream, too.

```text
report:
    title: "Stock report"
    table: "articles"
    columns:
        column:
            title: "Article number"
            select: "article_id"
        end
        column:
            title: "Margin of profit"
            css: "right-align"
            select: ( 100 1 "purchase_price" "selling_price" / - * 2 round )
            as "margin"
        end
    end
end
```

**JSON**

```json
{
    "title": "Lagerrapport",
    "table": "articles",
    "columns": [
        {
            "title": "Article number",
            "select": "article_id"
        },
        {
            "title": "Article number",
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

You easily see that the JSON format works excellent for structured data, as it was designed to do, but does not scale well for logical expressions (unless you want to write expressions as a string, in which case you need another lexer/parser anyway).

The Forth-like is tempting, but I think the post-fix notation is just too confusing for any non-technical (and technical...) person to follow and reason about.

_If_ I had access to a proper lexer/parser lib, I would consider a BASIC-format DSL, too. _And_ if BASIC had proper primitives for structured data...

In all these cases, the lexer/parser code is kinda trivial.

## Parse S-expression in PHP

Parsing an S-expression is easy enough in any language - use a tree of stacks and push/pop words and new stacks on the stacks, depending on when you encounter ')' or '(' or space characters.

The parser also has an `$inside_quote` state to be able to deal with quoted strings.

```php
function parse(string $sc)
{
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
        // Normalize string
        $s = trim((string) preg_replace('/[\t\n\r\s]+/', ' ', $s));
        // Two extra spaces for the while-loop to work
        $this->buffer = $s. '  ';
    }

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

    (select (round (* 100 (- 1 (/ purchase_price selling_price))) 2))

To build a SQL from an S-expression, it's a basic recursive evaluation:

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

## Summarizing totals using the DSL

Calculating totals is different than the SQL evaluation, because the end-result is a calculation, not a string.



## Constructing an HTML table form the DSL

todo

## Questions

> Why not just use a syntax that they're familiar with?

1. For office political reasons, I want the lexer/parser to be simple
2. The DSL might include SQL, PHP and JavaScript, in which case the syntax has to be different anyway

> From my experience of working with non-programmers, they are usually better off (and happier) using a GUI.

That would be really cool, but I lack the resources to do a proper report generation interface, and the mega-enterprise third-party softwares available does not hook into our framework very well.

## Previous work

There are lots of DSLs out there, but I found nothing for PHP and report generation.

There is one report DSL for Python that is not actively maintained anymore: https://github.com/kjosib/glowing-chainsaw
