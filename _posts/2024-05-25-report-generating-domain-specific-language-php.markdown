---
layout: post
title:  Report generating domain-specific language
date:   2024-05-25
categories: programming php
---

Why can't the customers write their own damn reports. Or the sales people. Or the technical support. They already know some basic SQL, right? How hard could it be to reate a safe subset of SQL, that also includes some HTML formatting capability? But the parser would have to be dirt simple. I don't want to en dup with a big pil eof code only I can maintain. Kind kind of languages work? S-expressions? Forth? JSON? It would have to be able to deal with complex calculations between database columns.

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

```txt
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
            select: ( "purchase_price" "selling_price" / 1 - 100 * 2 round )
            as "margin"
        end
    end
end
```

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

The Forth-like is tempting, but I think the post-fix notation is just too confusing for any non-technical (and technical...) person.

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



## Parse JSON in PHP

I'll leave out JSON, since PHP already has a `json_decode` function.
