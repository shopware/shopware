---
title: Improve extensibility ESI templates
issue: https://github.com/shopware/shopware/issues/8136
author: Michael Telgmann
author_github: @mitelg
---

# Storefront
* Added new global Twig template variables `headerParameters` and `footerParameters` to add query parameters to the ESI request of header and footer.

___

# Upgrade Information

## Improve extensibility of header and footer ESI templates

With this change it is possible to add query parameters to the header/footer ESI requests.
This could be used to customize the header/footer templates.

- Extending the `src/Storefront/Resources/views/storefront/base.html.twig` file:
```twig
{% sw_extends '@Storefront/storefront/base.html.twig' %}

{% block base_esi_header %}
    {% set headerParameters = headerParameters|merge({ 'vendorPrefixPluginName': { 'activeRoute': activeRoute } }) %}

    {{ parent() }}
{% endblock %}
```

- Within a plugin, you can also use the `Shopware\Storefront\Event\StorefrontRenderEvent`
```php
class StorefrontSubscriber
{
    public function __invoke(StorefrontRenderEvent $event): void
    {
        if ($event->getRequest()->attributes->get('_route') !== 'frontend.header') {
            return;
        }

        $headerParameters = $event->getParameter('headerParameters') ?? [];
        $headerParameters['vendorPrefixPluginName']['salesChannelId'] = $event->getSalesChannelContext()->getSalesChannelId();

        $event->setParameter('headerParameters', $headerParameters);
    }
}
```

After that you can use this data to customize the header template:
```twig
{% sw_extends '@Storefront/storefront/layout/header.html.twig' %}

{% block header %}
    {{ dump(headerParameters.vendorPrefixPluginName.activeRoute) }}
    {{ dump(headerParameters.vendorPrefixPluginName.salesChannelId) }}

    {{ parent() }}
{% endblock %}
```