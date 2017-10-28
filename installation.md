---
layout: page
title: Installation
permalink: /installation/
---

This page describes how to install the compiler SubsetPHP.

Usage
-----

The program `llvm_test myfile.php` will type-check and compile "myfile.php".

How to install on Ubuntu 14.04 LTS:
-----------------------------------

The installation might take up 1 GB of space (mostly clang/LLVM, php-src).

Install some basics:

```bash
apt-get install git make m4 unzip pkg-config autoconf re2c bison libxml2-dev ncurses-dev g++
```

Clone the git:

```bash
git clone https://github.com/olleharstedt/subsetphp
```

Install OCaml and friends:

```bash
apt-get install ocaml clang-3.6 llvm-3.6 llvm-3.6-dev
```

Install OPAM: 

```bash
wget https://raw.github.com/ocaml/opam/master/shell/opam_installer.sh -O - | sh -s /usr/local/bin/
opam init
eval `opam config env`
```

Upgrade OCaml to 4.02.3: 

```bash
opam switch 4.02.3
eval `opam config env`
```

Install ocamlfind and other necessary OCaml packages:

```bash
opam install ocamlfind ppx_deriving llvm.3.6
```

Download the PHP source:

```bash
cd subsetphp/
rm -r php-src/
git clone https://github.com/php/php-src
```

Configure and build PHP (make sure all steps finish completely):

```bash
cd php-src/
./buildconf
./configure
make
```

Run the make script. This will compile the compiler and then compile test.php into a binary "test".

```bash
make comp
```

Please report any errors during installation as a github issue.

How to install on DragonFly BSD 4.4:
------------------------------------

TODO
