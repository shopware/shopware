# Release Info

## 6.7.1.0

### Features

### API

### Core

### Administration

### Storefront

#### Improved Header and Footer Extensibility
Improved the extensibility of header and footer ESI templates to provide better customization options for developers:
* Added Twig blocks for extending header and footer templates
* Enhanced ability to add custom parameters to these templates
* Leveraged `StorefrontRenderEvent` to modify template parameters

This change makes it easier for developers to customize header and footer areas without overriding entire templates, enabling more maintainable customizations.

**Example: Extending a template with the new Twig blocks**
```twig
{% sw_extends '@Storefront/storefront/base.html.twig' %}

{% block base_esi_header %}
    {% set headerParameters = headerParameters|merge({ 'vendorPrefixPluginName': { 'activeRoute': activeRoute } }) %}

    {{ parent() }}
{% endblock %}
```

**Example: Using the StorefrontRenderEvent to modify parameters**
```php
<?php

namespace MyPlugin\Subscriber;

use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HeaderFooterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        if ($event->getRequest()->attributes->get('_route') !== 'my.special.route') {
            return;
        }

        $headerParameters = $event->getParameter('headerParameters') ?? [];
        $headerParameters['vendorPrefixPluginName']['salesChannelId'] = $event->getSalesChannelContext()->getSalesChannelId();

        $event->setParameter('headerParameters', $headerParameters);
    }
}
```

### App System

### Hosting & Configuration
