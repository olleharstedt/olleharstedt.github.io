---
layout: post
title:  Mocking with Amphp
date:   2020-08-03
categories: php
---

{:refdef: style="text-align: center;"}
<img src="{{ site.url }}/assets/img/mocking2.jpg" alt="Mocking" height="300px"/>
{: refdef}
{:refdef: style="text-align: center;"}
_Mocking can be hard_
{: refdef}

## Introduction

Mocking with PHPUnit is basically learning a new DSL. In this blog post I'll go through an alternative approach using promises and generators with the framework Amphp, to reduce the need for mocking significantly.

## Amphp

> Amp is an event-driven concurrency framework for PHP providing primitives to manage cooperative multitasking building upon an event loop, promises, and asynchronous iterators. 

[Amphp](https://amphp.org) is a concurrency framework for PHP which wraps IO in promises using generators. A nice example is given on their home page:

```php
try {
    $request = new Request("https://amphp.org/");
    $response = yield $http->request($request);

    if ($response->getStatus() !== 200) {
         throw new HttpException;
    }
} catch (HttpException $e)  {
    // Handle error
}
```

This is assumed to be wrapped in a [generator](https://www.php.net/manual/en/language.generators.overview.php) function. `$http->request($request)` returns a promise which is resolved by the main event loop and sent back in to the generator, here `$response`.

## Use-case

As a more elaborate use-case where business logic and side-effects are tightly engtangled, I'll use an example from a [Haskell article](https://degoes.net/articles/modern-fp):

```haskell
saveFile :: Path -> Bytes -> IO Unit
saveFile p f = do
  log ("Saving file" ++ show (name p) ++ " to " ++ show (parentDir p))
  r <- httpPost ("cloudfiles.fooservice.com/" ++ (show p)) f
  if (httpOK r) then log ("Successfully saved file " ++ show p)
  else let msg = "Failed to save file " ++ show p
  in log msg *> throwException (error msg)
```

This is a function that posts a binary resource to `cloudfiles.fooservice.com` and throws an exception if it fails. It also does logging. In PHP it would look something like this (assuming logger and http client are both injected):

```php
/** Saving $file to remote host address $path */
public function saveFile($path, $file)
{
    $this->log('Saving file ' . $path);
    $response = $this->httpClient->post($path, $file);
    if ($response->getStatus() === 200) {
        $this->log('Successfully saved file ' . $path);
    } else {
        $msg = 'Failed to save file ' . $path;
        $this->log($msg);
        throw new Exception($msg);
    }
}
```

Rewriting this in Amphp-style, we get:

```php
public function saveFile($path, $file)
{
    yield $this->logger->write('Saving file ' . $path);
    $request = new Request('https://cloudfiles.fooservice.com/', 'POST');
    $request->setBody($file);
    $response = yield $this->httpClient->request($request);
    if ($response->getStatus() === 200) {
        yield $this->logger->write('Successfully saved file ' . $path);
    } else {
        $msg = 'Failed to save file ' . $path;
        yield $this->logger->write($msg);
        throw new Exception($msg);
    }
}
```

## Purity

You might notice a certain property of our new `saveFile` method: it's _pure_. To recap from [Wikipedia](https://en.wikipedia.org/wiki/Pure_function), being pure means:

1. Its return value is the same for the same arguments (no variation with local static variables, non-local variables, mutable reference arguments or input streams from I/O devices).
2. Its evaluation has no side effects (no mutation of local static variables, non-local variables, mutable reference arguments or I/O streams).

The method does not _really_ write to the log file and it does not _really_ send a POST request to the cloud service - it just promises to do so. If the promises are not resolved by the main event loop, no side-effects will be executed.

## Mocking effects

To run the save file class with Amphp, you do:

```php
Loop::run(function() {
    $httpClient = HttpClientBuilder::buildDefault();
    $logger = yield File\open('log.txt', "c+");
    $fileSaver = new FileSaver($logger, $httpClient);
    yield from $fileSaver->saveFile('moo', 0);
});
```

Now, how would we test `saveFile`? We don't want to test the logger or the http client, because that's framework code. Instead we want to make sure `saveFile` reacts correctly in all scenarios. Especially that:

* Logging is done correctly
* Exceptions are thrown at failure

For logging, we want it to be correct at both success and failure. We can now test this without any fixture by checking that the correct promises are yielded from the generator. Example:


