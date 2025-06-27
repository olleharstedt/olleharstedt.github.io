---
layout: post
title:  Associative personal knowledge base
date:   2025-06-27
categories: personal knowledge base cli pkb
---

A concept so simple I wondered why I haven't thought of it before. And why it isn't already built-in in bash.

This is a command-line interface to a knowledge base, using association and ownership.

The interface consists of five basic commands:

* `put` to put a new entity in the database
* `desc` to describe an entity (either add new description or fetch one)
* `is-a` to add a is-a relation
* `has-a` to add a has-a relation
* `list` to list entities of a certain is-a and has-a

Basic example:

```
put olle    # Add new entity olle
put jonas   # Add new entity jonas
put friend  # Add new entity jonas
jonas is-a friend  # Add new is-a relation
olle has-a jonas   # Add new has-a relation
list olle friend   # List all entities that belong to olle and are a friend
```

You can also do

```bash
desc olle
```

and it will list all details for that entity:

```
[32] olle "Olle"
    has jonas (friend)
```
