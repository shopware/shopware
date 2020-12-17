[titleEn]: <>(Make storefront controller available in maintenance mode)
[metaDescriptionEn]: <>(This HowTo will show you how to make a storefront controller available in maintenance mode)
[hash]: <>(article:how_to_add_enable_controller_maintenance)

## Overview

This guide will show you how to make controller routes available in a maintenance mode situation.

## Route attributes

```php
<?php

use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;

/** @var Request $request */
$request->attributes->set(
    SalesChannelRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE,
    true
);
```

## Route annotation defaults

```php
<?php

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class Controller
{
    /**
     * @Route("my-page", defaults={"allow_maintenance"=true})
     */
    public function routeAction(): Response
    {
        return new Response();
    }
}
```
