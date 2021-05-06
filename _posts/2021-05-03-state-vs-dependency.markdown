---
layout: post
title:  A class should have state or dependencies but not both
date:   2021-05-03
categories: programming
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/scissors.png" alt="Dependencies" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
*Where to cut?*
{: refdef}

Classes should either have state _or_ have dependencies on IO classes, but not both.

Using three class types:

* Domain entity (point, user)
* Resource (database, file io)
* Command object

In pseudo-code:

```java
class Point implements Drawable
    private x, y
    construct(x, y)
    draw(SurfaceInterface surface)
```

In a worse design:

```java
class Point implements Drawable
    private x, y, surface
    construct(x, y, surface)
    draw()
```

Or possibly:

```java
class Point implements Drawable
    private x, y, surface
    construct(x, y)
    setSurface(surface)
    draw()
```

Now Point has three properties of widely different character. Two related to state, one releated to behaviour.

```java
class Rectangle implements Drawable
    internal bottomLeft, topRight
    construct(Point bottomLeft, Point topRight)
    draw(SurfaceInterface surface)

main()
    shapes = [
        new Point(1, 2),
        new Rectangle(new Point(2, 3), new Point(3, 4))
    ]
    surface = new Surface()
    forall shapes as shape
        shape.draw(surface)
```

```java
class AppConfig
    internal username, password
    construct(username, password)

class App
    internal id, config
    construct(id, config)

class InstallApp
    private db, io
    construct(db, io)
    execute(App app)
```

But not:

```java
class App
    internal id, config
    construct(id, config)
    install(DatabaseConnection db, FileIO io)
    
```

```java
main()
    config = new AppConfig("username", "password")
    app = new App(1, config)
    installApp = new InstallApp(
        new DatabaseConnection(),
        new FileIO()
    )
    installApp.execute(app)
```

## Notes

```
class PointPlotter implements PlotterInterface
    private surface
    construct(SurfaceInterface surface)
    plot(Point point)

class PointPersistance implements PersistanceInterface
    private pers
    construct(PersistanceInterface pers)
    save(Point point)
```

Point.toXML, .toJSON, .toSQL (column, rows)
