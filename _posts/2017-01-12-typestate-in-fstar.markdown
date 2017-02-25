---
layout: post
title:  Typestate-oriented programming in F*
date:   2017-01-12
categories: fstar
---

The meaning of this blog post is to investigate the possibility of doing typestate-oriented programming in F\*.

## 1. Introduction

[F\*](https://www.fstar-lang.org/) (pronounced "f star") is a new functional programming language with refinement types, effect types and incremental proving. Cool! So what does that mean?

### 1.1 Incremental proving

You don't _have_ to prove anything in F\* - its default is to assume ML-like effects and types, like in OCaml and F#. But you _can_ prove a lot of things. Some things are even proven _for_ you. Take a simple add function:

```ocaml
let add x y =
    x + y
```


The type of this function in OCaml or F# would be `int -> int -> int`, meaning a function that takes two integers and returns an integer. And that's more or less the end of a traditional functional type-system. F\* lets you go further. Let's investigate that.

The most basic type of `add` in F\* is

```ocaml
val add : int -> int -> int
```

This is the same as in OCaml/F#. We can also name the arguments:

```ocaml
val add : x:int -> y:int -> result:int
```

What happens if we let F\* infer the type automatically?

    $ ./bin/fstar.exe add.fst --log_types --print_effect_args
    [...]
    let add : (x:int -> y:int -> Tot int)

Almost the same, but with a `Tot` before the `int`. `Tot` means `Total`, meaning that `add` is a total function. From the [tutorial](https://www.fstar-lang.org/tutorial/):

> any expression that is inferred to have type-and-effect `Tot t`, is guaranteed (provided the computer has enough resources) to evaluate to a `t`-typed result, without entering an infinite loop; reading or writing the program's state; throwing exceptions; performing input or output; or, having any other effect whatsoever. 

So from the effect `Tot`, we know that `add` has no side-effects, that it will terminate and not throw any exception. That's a pretty good improvement from just `int` already!

Let's take the next step:

```ocaml
val add : x:int -> y:int -> Tot (result:int{result == x + y})
```

{% include tip.html icon="info-circle" text="<code>==</code> and <code>=</code> are in fact not the same things in F\*, where the former is on type level, the latter on value level." %}

Here we add the `Tot` effect, and also a _refinment_ on the return value of the function: `{result == x + y}`. If this signature type-checks, we have successfully (and trivially) proven that `add` does indeed return the sum of `x` and `y`.

    $ ./bin/fstar.exe add.fst
    Verified module: Add (168 milliseconds)
    All verification conditions discharged successfully

Oh joy! It's possible to scramble the function a bit without disturbing the proof:

```ocaml
val add : x:int -> y:int -> Tot (result:int{result == x + y})
let add x y =
    x + y -x + x  (* Still checks out *)
```

If the function contains an error, F\* will show an error message that points to the correct line in the program:

    val add : x:int -> y:int -> Tot (result:int{result == x + y})
    let add x y =
        x + y - 1

    $ ./bin/fstar.exe add.fst
    ./add.fst(7,4-7,13): (Error) Subtyping check failed; expected type (result#13:int{(eq2 result@0 (op_Addition x y))}); got type int (see ./add.fst(3,44-3,59))

In this way you can choose which part of your program you want to prove, and how much.

{% include tip.html icon="pencil" text="Can you write a function that is guaranteed to only return prime numbers? Tip: It's possible to use functions in the refinement clause, as long as they are total." %}

### 1.2 Refinement types

[Refinement types](https://en.wikipedia.org/wiki/Refinement_(computing)#Refinement_types) (also in the [turorial](https://www.fstar-lang.org/tutorial/tutorial.html#sec-refinement-types)) is a way to say that a type is not only an integer or a string, but an integer withint a certain interval or a string of a certain length. To be more precise, it defines a [predicate](https://en.wikipedia.org/wiki/Predicate_(mathematical_logic)) for a type. We saw this in the return type above for the function `add`, using the notation `{}` after a type. A common example of refinement types is the definition of the natural numbers, `n:int{n >= 0}`, but any other properties are indeed possible, e.g. files that are open or closed, as we will see below.

### 1.3 Effect types with pre- and post-conditions

F\* has a system of effects using monads. The effect we are interested in here is the `STATE` effect, used for proving stateful computations that writes and reads to the heap. Proving is done by writing pre- and post-conditions:

```ocaml
ST unit
    (requires (fun heap -> True))
    (ensures (fun heap result heap' -> True))
```

{% include tip.html icon="info-circle" text="<code>True</code> and <code>true</code> are in fact not the same things in F\*, where the former is on type level, the latter on value level." %}

Let's inspect this in more details.

`ST` is a short-hand for a specific version of the `STATE` monad. `unit` signifies that the function that uses this signature returns nothing, just like in OCaml, or like `void` in a couple of other languages. `requires` is the pre-condition of the effect. `ensures` is then of course the post-condition. As you can see, they both take a function as an argument. In those functions, you can use some ready made operations to control what's happening on the heap:

```ocaml
val sel : #a:Type -> heap -> ref a -> Tot a
val upd : #a:Type -> heap -> ref a -> a -> Tot heap
val contains : #a:Type -> heap -> ref a -> a -> Tot bool
```

where `sel` means "select" and `upd` means "update".

Let's make a simple example.

```ocaml
val only_add_to_ten : n:ref int -> ST int
    (requires (fun heap -> (sel heap n) == 10))
    (ensures (fun heap result heap' -> modifies_none heap heap'))
let only_add_to_ten x = 
    !x + 10

let () = 
    let a = 5 in
    let b = 5 in
    let x = alloc (a + b) in
    only_add_to_ten x
```

Here we have a function that will only compile if its argument is a reference to an integer with value 10, ensured by the pre-condition `(sel heap n) == 10`. Its post-condition states that nothing in the heaps are modified, ensured by the built-in function `modifies_none`. The bang before the `x` in the function body means de-referencing, taking the value at `x`.

In the small test, we use variables `a` and `b` to calculate x. `alloc` is used to allocate memory for a reference on the heap. If `a` and `b` do not equal to 10, the F\* compiler will show this error message:

    (Error) assertion failed (see ./onlyaddtoten.fst(7,27-7,45))


You might wonder what's the difference between this and, say, using assert or throwing an exception, but remember that this is done during compile time! _It's now possible to enforce client code of a module to execute functions in a certain order._


### 1.4 Semi-automatic proving

Above, the F\* compiler could _prove_ that `only_add_to_ten` would only accept references to integer 10. If the function was used in any other way, the program would not compile. Still, we as programmers didn't have to provide F\* with any manual proofs or tactics - the system did it automatically. How? By using the [SMT solver](https://en.wikipedia.org/wiki/Satisfiability_modulo_theories) [Z3](https://github.com/Z3Prover/z3). So how does F\* disperse the proofs to Z3, and how do you know what can and what cannot be proved automatically? That knowledge is way beyond me. I can only refer to the academic papers written by the F\* team. 

For an interesting case of how F\* can prove termination, see the example with the fibonacci function in the tutorial (chapter 5).

## 2. Typestate-oriented programming

Typestate-oriented programming is a programming paradigm outlined by Jonathan Aldrich et al in the paper [Typestate-oriented programming](http://www.cs.cmu.edu/~aldrich/papers/onward2009-state.pdf). The code snippet below describes the intution behind the concept pretty well:

```java
state File {
  public final String filename;
}

state OpenFile extends File {
  private CFilePtr filePtr;
  public int read() { ... }
  public void close() [OpenFile>>ClosedFile] { ... }
}

state ClosedFile extends File {
  public void open() [ClosedFile>>OpenFile] { ... }
}
```

Instead of classes, we declare states. A state is much like a class, but an object can change state during its lifetime; the state of the object is mirrored in the type-system. As you can see in the code above, it's not possible to open an already opened file, and not possible to close a closed file - the methods do not exist in those states. This check is thought to be done during compile-time. With this technique, a whole new area of bugs are available for compile-time checking.

Here's a small use-case example, also from the paper:

```java
int readFromFile(ClosedFile f) {
  openHelper(f);
  int x = computeBase() + f.read();
  f.close();
  return x;
}
```

In clear text, this function accepts a closed file, opens it, read a number from it, add the number to a dynamically computed base, close the file and returns the sum. The function is also guaranteed to leave file `f` closed at the end - if the file would be open, the signature would look like this:

```java
int readFromFile(ClosedFile>>OpenFile f)
```

signaling that `readFromFile` changes the state of `f` from closed to open.

{% include tip.html icon="question-circle" text="Do we know if <code>computeBase</code> has a reference to file <code>f</code>? How does that matter?" %}

The point of `readFromFile` is of course to show that `f.read()` would not compile if not `openHelper(f)` was called first. In other words, the interface of the state (class) `file` is preserved, even when it demands a certain order of function calls.

### 2.1 Using F\* to emulate typestate-oriented programming

Let's head over to F\* and use our new knowledge to mimic the behaviour of typestate-oriented programming. First, we need a simple enum to say if a file is open or closed:

```ocaml
type state = 
  | Open
  | Closed
```

We will save the state in a file record type:

```ocaml
type file = {
    name: string;
    state: ref state;
}
```

Note that `state` is a reference, that is, a mutable field, while `name` is immutable. In the real world, our `file` type would also include a file handler, but we will skip that part since it's not relevant for our example.

The next thing we need is a _predicate_, that is, a function on type level that returns `True` or `False`. We will use this predicate in our functions to say that a file has to be either opened or closed.

```ocaml
type isClosed file heap = (sel heap file.state) == Closed
type isOpen file heap = (sel heap file.state) == Open
```

Now we can write our own `openHelper`, that will take a closed file and open it:

```ocaml
val openHelper : file:file -> ST unit
    (requires (fun heap -> isClosed file heap))
    (ensures (fun heap result heap' -> isOpen file heap'))
let openHelper file =
    file.state := Open
```

{% include tip.html icon="info-circle" text="The operator <code>:=</code> means updating a mutable variable with a new value." %}

Based on earlier examples, the meaning of this function should be pretty clear.

The `read` function takes an open file and guarantees to leave it open at the end. Again the body of the function is a dummy, that in real life actually would read from a file.

```ocaml
val read : file:file -> ST int
    (requires (fun heap -> isOpen file heap))
    (ensures (fun heap result heap' -> isOpen file heap'))
let read file =
    13
```

`computeBase` is a simple, total function:

```ocaml
val computeBase : unit -> Tot int
let computeBase () =
    12
```

We can now complete our version of `readFromFile`:

```ocaml
val readFromFile : file:file -> ST int
    (requires (fun heap -> isClosed file heap))
    (ensures (fun heap result heap' -> isClosed file heap'))
let readFromFile file =
    openHelper file;
    let x = computeBase () + read file in
    file.state := Closed;
    x
```

As you can see, this function takes a file and returns an integer. Further more, we're using the `ST` effect. From that we know that it modifies the heap. Taking a look at the pre- and post-conditions, they require that the file is closed both before and after the function.

## 3. Discussion

The big question is how much overhead is required to ensure this kind of interface.

OK, so let's have a look at the complete code listing:


```ocaml
module Typestate

open FStar.Heap
open FStar.ST

(* Enum-like data type for file state *)
type state = 
  | Open
  | Closed

(* Record type for file. In real life, it would also include a
 * file handler. Note that state is a reference, meaning mutable
 * variable, unlike name, which is immutable. *)
type file = {
    name: string;
    state: ref state
}

(* Our two predicates to decide if a file is state opened or
 * closed. *)
type isClosed file heap = (sel heap file.state) == Closed
type isOpen file heap = (sel heap file.state) == Open

(* A function that opens a file. Again, in real life it
 * would actually open a file handler. *)
val openHelper : file:file -> ST unit
    (requires (fun heap -> isClosed file heap))
    (ensures (fun heap result heap' ->
        isOpen file heap'
        ))
let openHelper file =
    file.state := Open

(* This reads from a file. Also just a dummy function.
 * What's interesting is the pre- and post-conditions. *)
val read : file:file -> ST int
    (requires (fun heap -> isOpen file heap))
    (ensures (fun heap result heap' -> isOpen file heap'))
let read file =
    13

val computeBase : unit -> Tot int
let computeBase () =
    12

val readFromFile : file:file -> ST int
    (requires (isClosed file))
    (ensures (fun heap s heap' -> isClosed file heap'))
let readFromFile file =
    openHelper file;
    let x = computeBase () + read file in
    file.state := Closed;
    x

(* Small test *)
let () =
    let file1 = {
        name = "file1";
        state = alloc Closed;
    } in

    readFromFile file1
```

## 4. Further reading

Tut, slides, IRC? slack? mailing list? reddit/r/fstar? roadmap? use-case? extract to ocaml/f#
