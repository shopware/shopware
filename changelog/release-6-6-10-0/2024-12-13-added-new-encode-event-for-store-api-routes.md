---
title: Added new .encode event for store api routes
issue: #5820
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
author_github: OliverSkroblin
---

# API

* Added new `.encode` event for store api routes

___
# Upgrade Information

## New `.encode` event for store api routes

This new event allows you to extend the response data of store api routes on a event based approach. The event is triggered after the data has been fetched and before it is returned to the client.

```php
<?php

#[\Symfony\Component\EventDispatcher\Attribute\AsEventListener(
    event: 'store-api.product.listing.encode', 
    priority: 0, 
    method: 'onListing'
)]
class MyListener
{
    public function onListing(\Symfony\Component\HttpKernel\Event\ResponseEvent $event): void
    {
        $response = $event->getResponse();
           
        assert($response instanceof \Shopware\Core\System\SalesChannel\StoreApiResponse);
    }
}

```
