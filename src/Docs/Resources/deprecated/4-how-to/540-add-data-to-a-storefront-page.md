[titleEn]: <>(Add data to a storefront page)
[metaDescriptionEn]: <>(This HowTo will give an example on adding data to a storefront page.)
[hash]: <>(article:how_to_add_storefront_data)

## Overview

Pages or Pagelets are the objects that get handed to the templates and provide all necessary information for the template to render.
For more information of the concepts behind Pages and Pagelets look [here](./../2-internals/3-storefront/10-composite-data-loading.md).
If you make template changes you probably want to display some data that is currently not available in the page.
In this case you will have to listen on the page loaded event and then load the additional data and add it to the page object. 
This HowTo will show you how to achieve this, by adding the total number of active products to the footer pagelet and displaying them in the storefront.

## Register to an Event

In order to register to an Event you first have to know to which event you want to register your subscriber. All Pages or Pagelets throw loaded Events and this is the right event to subscribe to if you want to add data to the page.
You find more information on how to register to events in this [HowTo](./040-register-subscriber.md).
In our case we want to add data to the Footer Pagelet so we need to subscribe to the `FooterPageletLoadedEvent`.

```php
<?php declare(strict_types=1);

namespace Swag\ExtendPage\Storefront\Subscriber;

use Shopware\Storefront\Pagelet\Footer\FooterPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FooterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
          FooterPageletLoadedEvent::class => 'addActiveProductCount'  
        ];
    }

    public function addActiveProductCount(FooterPageletLoadedEvent $event): void
    {
    }
}
```

The next thing we need to do is register our subscriber in the DI-Container and tag it as an event subscriber:

```xml
<!-- in Resources/config/services.xml -->
<service id="Swag\ExtendPage\Storefront\Subscriber\FooterSubscriber">
    <tag name="kernel.event_subscriber"/>
</service>
```

## Add data to the page

Now that we have registered our Subscriber to the right event we first need to fetch the additional data we need and then add it as an extension to the pagelet:

```php
    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function __construct(EntityRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function addActiveProductCount(FooterPageletLoadedEvent $event): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', true));
        $criteria->addAggregation(new CountAggregation('productCount', 'product.id'));

        /** @var CountResult $productCountResult */
        $productCountResult = $this->productRepository
            ->search($criteria, $event->getContext())
            ->getAggregations()
            ->get('productCount');

        $event->getPagelet()->addExtension('product_count', $productCountResult);
    }
```

and we have to adjust our service definition to inject the product repository:

```xml
<service id="Swag\ExtendPage\Storefront\Subscriber\FooterSubscriber">
    <argument type="service" id="product.repository"/>
    <tag name="kernel.event_subscriber"/>
</service>
```


The whole subscriber now looks like this:

```php
<?php declare(strict_types=1);

namespace Swag\ExtendPage\Storefront\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FooterSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function __construct(EntityRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
          FooterPageletLoadedEvent::class => 'addActiveProductCount'
        ];
    }

    public function addActiveProductCount(FooterPageletLoadedEvent $event): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', true));
        $criteria->addAggregation(new CountAggregation('productCount', 'product.id'));

        /** @var CountResult $productCountResult */
        $productCountResult = $this->productRepository
            ->search($criteria, $event->getContext())
            ->getAggregations()
            ->get('productCount');

        $event->getPagelet()->addExtension('product_count', $productCountResult);
    }
}
```

and the services.xml looks like:

```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        <service id="Swag\ExtendPage\Storefront\Subscriber\FooterSubscriber">
            <argument type="service" id="product.repository"/>
            <tag name="kernel.event_subscriber"/>
        </service>
    </services>
</container>
```

## Displaying the data in the storefront

To display the additional data we need to override the footer template and render the data.
You can find detailed information on how to extend templates and override blocks [here](./250-extending-storefront-block.md).

For our case we extend the footer template and add a new column to the navigation block:

```twig
<!-- in Resources/views/storefront/layout/footer/footer.html.twig -->
{% sw_extends '@Storefront/storefront/layout/footer/footer.html.twig' %}

{% block layout_footer_navigation_columns %}
    {{ parent() }}

    {% if page.footer.extensions.product_count %}
        <div class="col-md-4 footer-column">
            <p>This shop offers you {{ page.footer.extensions.product_count.count }} products</p>
        </div>
    {% endif %}
{% endblock %}
```

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-extend-page).


