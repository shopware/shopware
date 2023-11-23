---
title: Use `ResetInterface` to reset instance state during requests
date: 2022-03-09
area: core
tags: [php, architecture, performance]
---

## Context

In many places, we use [memoization](https://en.wikipedia.org/wiki/Memoization) to store data inside an instance variable 
to cache the data and reuse it during the same request without needing to recompute/fetch it again.

Currently, we do not reset that data and rely on the fact that for every request, the kernel will be rebooted and each request will have new instances of those services (like PHP-FPM does).
With modern php application servers (e.g., roadrunner, swoole) that is not the case anymore and service instances maybe shared and reused for multiple requests.

## Decision

Symfony provides a way to reset data in between requests with the [`kernel.reset`](https://symfony.com/doc/current/reference/dic_tags.html#kernel-reset)-tag.
A class that holds memoized data in an instance variable, needs to provide a method to reset that data, and the service has to be tagged accordingly in the DI-container.

For consistency, we implement the `\Symfony\Contracts\Service\ResetInterface` which will add a `public function reset(): void`, where the reset is performed. 
The only exceptions to this rule are services that already implement a `reset`-method, and thus we cannot add one, in that case we will add a method with a different suitable name to reset the internal state, and configure that method to be used to reset data in the service tag.

This way we can build part of shopware already in a way that is compatible with modern PHP applications servers, and we are future-proof.
This reset is especially important in the cloud environment, as there the next request may be for different shop/tenant, so if we won't reset the data, we would not just serve stale data, but we wil instead data from a different instance!
Additionally, it makes unit testing easier, as PHPUnit already reuses service instances between execution of each test cases which already made trouble in the past.

## Consequences

Wherever we have a class that holds some memoized data in an instance variable e.g.
```php
class FooService
{
    private array $data = [];
    
    public function getData(): array
    {
        if ($this->data) {
            return $this->data;
        }
        
        return $this->data = $this->fetchDataFromSomewhere();
    }
}
```

We will implement the `ResetInterface` and provide a `reset()` method, to reset that internal state between requests:
```php
use Symfony\Contracts\Service\ResetInterface;

class FooService implements ResetInterface
{
    private array $data = [];
    
    public function getData(): array
    {
        if ($this->data) {
            return $this->data;
        }
        
        return $this->data = $this->fetchDataFromSomewhere();
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
```

And additionally we will tag the service with the `kernel.reset` tag:
```xml
<service id="FooService">
    <tag name="kernel.reset" method="reset"/>
</service>
```

That way, symfony will reset the data between requests automatically.

Additionally, we've added a hook to our `IntegrationTestBehaviour`, that will also reset that state between the execution of test cases.

This makes it unnecessary to reset the internal state manually by using `Reflection` to overwrite and reset the internal/private instance variable.
If you need to do this in your test case it clearly shows that you should go with the `ResetInterface` and `kernel.reset` tag instead!
Having to rely on `Reflection` in your test cases to reset data is a major red flag now!
