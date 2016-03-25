---
layout: page
title: Installation
permalink: /installation/
---

Usage
-----

The program `llvm_test myfile.php` will type-check and compile "myfile.php".

How to install on Ubuntu 14.04 LTS:
-----------------------------------

The installation might take up 1 GB of space (mostly clang/LLVM, php-src).

0. Install some basics:

  apt-get install git make m4 unzip pkg-config autoconf re2c bison libxml2-dev

1. Clone the git:

  git clone https://github.com/olleharstedt/subsetphp

2. Install OCaml and friends:

  apt-get install ocaml clang-3.6 llvm-3.6

3. Install OPAM: 

```bash
wget https://raw.github.com/ocaml/opam/master/shell/opam_installer.sh -O - | sh -s /usr/local/bin/
opam init
eval `opam config env`
```

6. Upgrade OCaml to 4.02.2: 

```bash
opam switch 4.02.2
eval `opam config env`
```

8. Install ocamlfind and other necessary OCaml packages:

```bash
opam install ocamlfind ppx_deriving.2.0
```

9. Download the PHP source:

```bash
cd subsetphp/
rm -r php-src/
git clone https://github.com/php/php-src
```

10. Configure and build PHP (make sure all steps finish completely):

```bash
cd php-src/
./buildconf
./configure
make
```

10. Run the make script. This will compile the compiler and then compile test.php into a binary "test".

```bash
make comp
```

Please report any errors during installation as a github issue.

How to install on DragonFly BSD 4.4:
------------------------------------

TODO
