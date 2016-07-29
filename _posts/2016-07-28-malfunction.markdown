Dear friends,

Back from vacation, ready to take another go with SubsetPHP. After a couple of months break, I plan to take another approach: Replacing the LLVM backend with an OCaml intermediate representation (IR) called [Malfunction](https://github.com/stedolan/malfunction) (alpha project). The point would be to "hook" into the OCaml compiler and generate a syntax the compiler can compile, (not being the OCaml language it self of course).

This is the hello world-example from Malfunction:

		(module
			(_ (apply (global $Pervasives $print_string) "Hello, world!\n"))
			(export))


What's happening here is a small program calling function `print_string` from module `Pervasives` with argument `Hello world!\n`, wrapped in a nameless module. If this looks a lot like Lisp to you, it's because it's based on a syntax convention called S-expressions ([symbolic expressions](https://en.wikipedia.org/wiki/S-expression)).

The biggest pro of using this system instead of LLVM is the GC that comes along with OCaml. I doubt it will be possible to make a "real" GC to LLVM without programming an LLVM plugin, something that's over my head and also out of my interest zone (I wanted to bring static types to PHP, not make a garbage collector).

So, despite LLVM IR being awesome and great, the next goal will be a benchmark using Malfunction and OCaml. Expect a new e-mail in 3 months. ;) (Hopefully less...)

I wish you all a nice summer!

Olle
Hamburg
