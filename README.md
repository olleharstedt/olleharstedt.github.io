Ideas for articles:

* Desing pattern grime
* Common types of classes in web dev

## Design pattern grime

https://www.cs.montana.edu/techreports/1516/Griffith.pdf

https://www.cs.montana.edu/izurieta/pubs/esem2010.pdf

## Common typees of classes in web dev

Why - same as for design patterns, increasing knowledge, apply common patterns and idioms.

Assuming the domain of web site and web applications (although they can be vastly different), which types of classes do you really need to come by these days? I'm leaning more and more towards these four:

* Command object
* Data-transfer object (immutable)
* Wrapper
* Builder (command query builder, HTTP message builder)
* (Observer, possibly a fifth one)

Command object takes care of requests. DTO is used to pass data around. Wrappers are needed for compatibility, file and database IO, mocks. Builders to have sane SQL queries, regular expressions, etc.i
