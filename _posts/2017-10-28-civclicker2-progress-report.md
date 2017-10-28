---
layout: post
title:  CivClicker 2 - progress report
date:   2017-10-28
categories: civclicker
---

Some months ago I was inspired by [deathraygames'](http://deathraygames.com/play-online/civ-clicker/) new version of [CivClicker](http://civclicker.sourceforge.net/) and decided I wanted to add a stoneage era into the game. This is a quick report about my progress so far. If you don't know what CivClicker is, click on one of the previous links and check it out! (In short: It's an incremental browser game in a Civilization-like setting.)

The development version is testable - not playable - [here](http://31.24.227.106/civclicker-2/).

To get more frequent news and updates, subscribe to the [CivClicker subreddit](http://reddit.com/r/civclicker).

## Interface

To get more room for new resources etc, I created a sidebar. I also applied bootstrap styling so it would be more easy to change style in the future.

![New GUI]({{ site.url }}/assets/img/civclicker_new_gui.png)

## Tools

I've added the possibilities to equip the citizens with different tools. Right now, there's only the hand axe tool - the most ancient tool that exists - but this will of course expand in the future.

Equipment gives the player interesting choices to configure the population to meet new situations.

![Tools1]({{ site.url }}/assets/img/civclicker_tools1.png)

![Tools2]({{ site.url }}/assets/img/civclicker_tools2.png)

## Weather

I've added a weather simulation based on the [Climak](https://www.researchgate.net/publication/228543743_Climak_A_stochastic_model_for_weather_data_generation) stochastic model. The implementation is far from perfect, but hopefully even a basic version will add some depth. Now the player has to make sure to keep his/hers civilization warm and bright to fight off cold, disease and wild animals!

Current weather and temperature can be seen in the top bar.

{:refdef: style="text-align: center;"}
![Weather]({{ site.url }}/assets/img/civclicker_weather.png)
{: refdef}

## Active buildings

Buildings can be activated/deactivated to consume resources. The use-case is pretty obvious.

![Building]({{ site.url }}/assets/img/civclicker_building.png)

## Culture points

A small but important change is the introduction of _culture points_, which lets the player purchase upgrades instead of with basic resources. Hopefully, this will open up to more different strategies (to invent agriculture, you don't have to gather 1000 food, but can instead collect culture points from a number of different options, like creating a small wonder, increase your population or free land, wage war, etc).

{:refdef: style="text-align: center;"}
![Culture]({{ site.url }}/assets/img/civclicker_culture.png)
{: refdef}

## Factorizations

A bunch of factorizations are being made to the code, most notably a plugin system that communicates with events and message passing to decrease coupling, and more thorough object-oriented design. The language used is now ES6, not ES5. Yes, that's right.

## TODO

Lots and lots. Some big stuff:

* Animal population simulation
* Crop simulation
* Icon overhaul

## Release

Too early to talk about, really, but within a year, I'd guess.

## Help wanted

If you want to help, feel free to join in on the discussion at [github](https://github.com/olleharstedt/civ-clicker)!

Also, a big thank you to [SkyHawkB](https://github.com/SkyHawkB) for helping with ideas, feedback and pull requests!
