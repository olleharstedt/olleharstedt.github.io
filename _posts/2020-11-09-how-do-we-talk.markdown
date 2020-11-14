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

<div style='margin: 1em 3em;'>
<table>
<tr>
<td><span class='fa fa-icon fa-info-circle fa-2x'></span></td>
<td>An introduction to quality attributes can be found <a href="https://medium.com/@nvashanin/quality-attributes-in-software-architecture-3844ea482732">here</a>.</td>
</tr>
</table>
</div>

In a team consensus matters. If you don't want to be completely authoritarian and simply decide what best practice means in your company (if you happen to have this authority), then you need to discuss it, write it down, and follow through in code reviews or enforce it with analysis tools. This is not always an easy process for developers with vast difference in experience, education, background. We all talk in different ways. We all assume different social rules. Teams span across generations, genders and cultures. I think it's worth a moment of our time to think about how we discuss technical topics together.

## How do we argue?

We already know _why_ we argue, or discuss. We do it because we're concerned, concerned that we will have to maintain someone else's spaghetti mess, fix someone else's bugs, failure at project level, going over budget, angry customers or bosses, _not following best practices_, or concerned of just being bored at work. We all feel and think something is at stake when we develop, and what you do today will affect me tomorrow and vice versa. So how do we approach this, together, as a team and community? Which rules do we follow when we argue about best practices?

Maybe we use different:

* Tone
* Vocabulary
* Rules (explicit or hidden)

[Discourse analysis](https://en.wikipedia.org/wiki/Discourse_analysis).

The idea of [language games](https://en.wikipedia.org/wiki/Language_game_(philosophy)) was invented by the philosopher Wittgenstein, basically saying/highlighting bla bla bla, rules in different contexts and sublanguages, it's not always clear to say if someone is following a rule or not, or what it means to follow a rule. The meaning of a word is how its used.

The man called "Uncle Bob", often used as authority.

How much do we care? Blunt vs polite. Politcal vs principled. Business oriented or idealistic.

There's a pretty clear goal when we talk about best practices: to reach consensus on how to achieve quality attributes. It's easy enough to analyse a short function, but when you have a code-base spanning 20 years, +300 contributors, arguments tend to get more abstract and use more intution.

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

Voting to make design decisions.

## A case study

Consider the following dialogue:

-- Since we use framework *F*, we should follow the idioms and design patterns present in that framework.<br/>
-- But *F* is really old. It's programmed in language *L*. I think we should apply design patterns that are more modern and has developed in language *L* since *F* was made.<br/>
-- But then we will have inconsistency in our code-base.<br/>
-- Yes, but it's better to have good and bad code, than only bad code. This is the only way to improve the code incrementally.<br/>
-- I'm not sure. Won't that increase the barrier to entry for new developers and make the code harder to read?<br/>
-- It will be harder to read, but the modern parts will be eaiser to test and maintain. It's worth the trade-off, I think.<br/>
-- What's wrong with the current way we test with *F*?<br/>
-- The tests are too slow, which gives the developers a too long feedback loop when they program.<br/>
-- But if we always apply the most modern idioms, we become victims to hype-driven development, and the design patterns will change from year to year. How can we reach stability or architectural integrity like this? Maybe we should update to framework *F2* instead, which includes more modern idioms?<br/>
-- That would take too long. We don't have the money.<br/>

Which rules and modes are present here? Lots of concerns and priorities. Hidden agendas? Experimental vs conservative.

A game moving from concern -> solution -> trade-off. Both concerns and solutions can be logos and ethos, appeal to logic or authority.

## Ethos vs logos

Paraphrasing a discussion I once took part of:

-- How come this list is not made with the list item HTML tag, but instead with div?<br/>
-- That's how Bootstrap does it, and we use Bootstrap.<br/>
-- But it's a _list_! It's semantically a list. Lists should be represented with list elements, not with divs.<br/>
-- Look, Bootstrap knows what they're doing. They work with CSS and HTML all day long. We can trust them.<br/>

Two type of arguments are clashing here: Logos (using logical arguments), and ethos (appeal to (external) authority, in this case the Bootstrap team). In the end, the Bootstrap person got tired of arguing and yielded. Whether or not Bootstrap actually _does_ use lists or divs is not relevant for the structure of the argument.

## Further reading

Similar topics: Personality type (colours).
