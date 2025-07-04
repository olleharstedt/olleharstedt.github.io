---
layout: post
title:  Web development is a solved problem
date:   2025-06-30
categories: programming web development design pattern
---

DRAFT

Maybe I've stagnated. Maybe I'm not being challenged.

Stagnated on a handful of design patterns than can solve 99% of problems thrown at it.

* Pipeline
* Command object for steps in pipeline
* Data transfer object and intermediate representations for communication between pipeline steps
* Events for extensibility
* Message queue for async processing
* Abstract base-class for code reuse, with shallow inheritance chain

Tools that helps:

* Static analysis
* Unit test
* Integrity test

Process development

Does not solve extreme scalability issues.

More advanced design patterns:

* Internal or external DSL, e.g. fluid interface like a query builder.
