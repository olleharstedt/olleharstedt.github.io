---
layout: post
title:  How do we talk? The language game we call "best practices"
date:   2020-11-09
categories: programming
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/wittgenstein.jpg" alt="y tho" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
*Ludwig Wittgenstein, the inventor of [language games](https://plato.stanford.edu/entries/wittgenstein/#LangGameFamiRese)*
{: refdef}

## Introduction

What are "best practices" in software engineering? Basically a couple of rules of thumb we use to ensure some quality attributes we deem important, like testability, readability, maintainability. We've all read blog posts about it, we've read books, seen lectures, and we all talk about it. But _how_ do we talk about it? How do we talk about anything? And does it matter?

In a team consensus matters. If you don't want to be completely authoritarian and simply decide what best practice means in your company (if you happen to have this authority), then you need to discuss it, write it down, and follow through in code reviews or enforce it with analysis tools. This is not always an easy process for developers with vast difference in experience, education, background. We all talk in different ways. We all assume different social rules. Teams span across generations, genders and cultures. I think it's worth a moment of our time to think about how we discuss technical matters together.

## Discourse theory and language games

Maybe we use different:

* Tone
* Vocabulary
* Rules (explicit or hidden)

[Discourse analysis](https://en.wikipedia.org/wiki/Discourse_analysis).

The idea of [language games](https://en.wikipedia.org/wiki/Language_game_(philosophy)) was invented by the philosopher Wittgenstein, basically saying/highlighting bla bla bla, rules in different contexts and sublanguages, it's not always clear to say if someone is following a rule or not, or what it means to follow a rule.

The man called "Uncle Bob", often used as authority.

There's a pretty clear goal when we talk about best practices: to reach consensus on how to achieve quality attributes. It's easy enough to analyse a short function, but when you have a code-base spanning 20 years, +300 contributors, you need to use intution and heuristic. Or?

Aristotle's categories of argumentation:

* Logos (logical appeal)
* Ethos (appeal to authority)
* Pathos (appeal to emotion)

Pathos: Often emotional words like "dirty" or "clean", "messy", "beautiful", "elegant".

How do we talk about "best practices"? Consensus and authority. Affect the behaviour of the team.

How do we convince other programmers about what is "good practices"?

Seniors should, to justify their position and salary, have the best arguments, on avarage. Senior could convince another senior, but not a junior.

Pretty vague quality attributes like "readability", "maintainability", "testability", attributes that matter more when a project grows in size and time.

What is a language game? A point of view on language and human interaction. Status is one language game that matters a lot when talking about technology. Different words carry different status.

Appeal to authority, external or internal. "Trust the framework, they know what's idiomatic." Or, "trust me, I have lots of experience, I know what's best."

## A dialogue

-- Since we use framework F, we should follow the idioms and design patterns present in that framework.

-- But F is really old. It's programmed in language P. I think we should apply design patterns that are more modern and has developed in language P since F was made.

-- But then we will have inconsistency in our code-base.

-- Yes, but it's better to have good and bad code, than only bad code. This is the only way to improve the code incrementally.

-- I'm not sure. Won't that increase the barrier to entry for new developers and make the code harder to maintain longterm?

Similar topics: Personality types.
