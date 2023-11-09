---
title: Add esi tags
issue: NEXT-30261
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Deprecated all classes in `Shopware\Storefront\Framework\Cache\ReverseProxy` domain. Domain will be moved to core and will be marked as internal, except `Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway`
* Deprecated all classes in `Shopware\Storefront\Framework\Cache` domain. Domain will be moved to core and will be marked as internal.
* Deprecated `\Shopware\Core\HttpKernel`, use `\Shopware\Core\Framework\Adapter\Kernel\KernelFactory::create` to create the kernel
* Deprecated `\Shopware\Core\Kernel::__construct`, will be internal and parameter will be all required. Use KernelFactory instead to initialize the kernel
* Deprecated `\Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent`, use `\Shopware\Core\Framework\Routing\Event\HttpCacheHitEvent` instead
* Deprecated `\Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent`, use `\Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent` instead
* Deprecated `\Shopware\Storefront\Framework\Cache\Event\HttpCacheItemWrittenEvent`, use `\Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheStoreEvent` instead
* Deprecated `\Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway`, reverse proxy will be moved to core, use `\Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway` instead  
* Deprecated `\Shopware\Storefront\Framework\Cache\ReverseProxy\FastlyReverseProxyGateway`, class will be moved to core and becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyGatewayRedisReverseProxyGateway`, class will be moved to core and becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache`, class will be moved to core and becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCacheClearer`, class will be moved to core and becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\ReverseProxy\VarnishReverseProxyGateway`, class will be moved to core and becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\AbstractHttpCacheKeyGenerator`, class will be removed, use `\Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent` instead
* Deprecated `\Shopware\Storefront\Framework\Cache\CacheStateValidator`, class becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\CacheStateValidatorInterface`, class becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\CacheStore`, class becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\CacheTracer`, class becomes internal
* Deprecated `\Shopware\Storefront\Framework\Cache\HttpCacheKeyGenerator`, class becomes internal and will be moved to core comain
___
# Upgrade Information
## Cache rework preparation
With 6.6 we are marking a lot of HTTP Cache and Reverse Proxy classes as @internal and move them to the core. 
We are preparing a bigger cache rework in the next releases. The cache rework will be done within the v6.6 version lane and and will be released with 6.7.0 major version. 
The cache rework will be a breaking change and will be announced in the changelog of 6.7.0. We will provide a migration guide for the cache rework, so that you can prepare your project for the cache rework.

You can find more details about the cache rework in the [shopware/shopware discussions](https://github.com/shopware/shopware/discussions/3299)

Since the cache is a critical component for systems, we have taken the liberty of marking almost all classes as @internal for the time being. However, we have left the important events and interfaces public so that you can prepare your systems for the changes now.
Even though there were a lot of deprecations in this release, 99% of them involved moving the classes to the core domain.

But there is one big change that affects each project and nearly all repositories outside which are using phpstan. 

### Kernel bootstrapping
We had to refactor the Kernel bootstrapping and the Kernel itself. 
When you forked our production template, or you boot the kernel somewhere by your own, you have to change the bootstrapping as follows:

```php

#### Before #####

$kernel = new Kernel(
    environment: $appEnv, 
    debug: $debug, 
    pluginLoader: $pluginLoader
);

#### After #####

$kernel = KernelFactory::create(
    environment: $appEnv,
    debug: $debug,
    classLoader: $classLoader,
    pluginLoader: $pluginLoader
);


### In case of static code analysis

KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

/** @var StaticAnalyzeKernel $kernel */
$kernel = KernelFactory::create(
    environment: 'phpstan',
    debug: true,
    classLoader: $this->getClassLoader(),
    pluginLoader: $pluginLoader
);

```

### Session access in phpunit tests
The way how you can access the session in unit test has changed.
The session is no more accessible via the request/response.
You have to use the `session.factory` service to access it or use the `SessionTestBehaviour` for a shortcut

```php
##### Before

$this->request(....);

$session = $this->getBrowser()->getRequest()->getSession();

##### After

use SessionTestBehaviour;

$this->request(....);

// shortcut via trait 
$this->getSession();

// code behind the shortcut
$this->getContainer()->get('session.factory')->getSession();

```

### Manipulate the http cache
Since we are moving the cache to the core, you have to change the way you can manipulate the http cache. 

1) If you decorated or replaced the `src/Storefront/Framework/Cache/HttpCacheKeyGenerator.php`, this will be no more possible in the upcoming release. You should use the http cache events
2) You used one of the http cache events --> They will be moved to the core, so you have to adapt the namespace+name of the event class. The signature is also not 100% the same, so please check the new event classes (public properties, etc.)

```php

#### Before

<?php

namespace Foo;

use Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheItemWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Subscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            HttpCacheHitEvent::class => 'onHit',
            HttpCacheGenerateKeyEvent::class => 'onKey',
            HttpCacheItemWrittenEvent::class => 'onWrite',
        ];
    }
}

#### After
<?php

namespace Foo;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheHitEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheStoreEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Subscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            HttpCacheHitEvent::class => 'onHit',
            HttpCacheKeyEvent::class => 'onKey',
            HttpCacheStoreEvent::class => 'onWrite',
        ];
    }
}



```

### Own reverse proxy gateway
If you implement an own reverse proxy gateway, you have to change the namespace of the gateway and the event.

```php
#### Before

class RedisReverseProxyGateway extends \Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway
{
    // ...
}


#### After

class RedisReverseProxyGateway extends \Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway
{
    // ...
}
```

### Http cache warmer

We deprecated all Http cache warmer, because they will be not usable with the new http kernel anymore. 
They are also not suitable for the new cache rework or for systems which have a reverse proxy or a load balancer in front of the shopware system.
Therefore, we marked them as deprecated and will remove them in the next major version.
You should use instead a real website crawler to warmup your desired sites, which is much more suitable and realistic for your system.


