[titleEn]: <>(Using Service Tags)
[wikiUrl]: <>(../plugin-system/using-service-tags?category=shopware-platform-en/plugin-system)

In this guide, you will learn how to use service tags in `Shopware`, which service tags exist and what they are used for.
Service tags in `Shopware` are the same as [Symfony - Service Tags](https://symfony.com/doc/current/service_container/tags.html).
They are used to register your service in some special way. 

## Shopware Service Tags
Below you can find a listing of each service tag that exists in `Shopware`.
Some tags are links and will provide you with further information.

| Tag                                                                        | Required Arguments     | Usage                                                                                                   |
|----------------------------------------------------------------------------|------------------------|--------------------------------------------------------------|
| [shopware.extension](./#shopware.extension)                                | *none*                 | This tag lets you create a shopware event subscriber         |
| [shopware.entity.definition](../20-data-abstraction-layer/1-definition.md) | *entity*               | This tag is used to make your entities system-wide available |
| shopware.feature                                                           | *flag*                 | This tag is used internally as a feature flag for VCS        |

## shopware.extension
Let's say you want to sort each product listing descending by price.
Start by adding the `shopware.extension` tag to your service definition:
 
```xml
...

<service id="SwagExample\Subscriber\ListingSubscriber">
    <tag name="shopware.extension"/>
</service>

...
```
*Service definition*

To sort each listing descending, you need to add sorting to the `Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria`:

```php
<?php declare(strict_types=1);

namespace SwagExample\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Storefront\Listing\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Listing\ListingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ListingSubscriber implements EventSubscriberInterface
{
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::CRITERIA_CREATED => ['addSorting', 10],
        ];
    }

    public function addSorting(PageCriteriaCreatedEvent $event): void
    {
        $criteria = $event->getCriteria();

        $criteria->addSorting(new FieldSorting('product.price', FieldSorting::DESCENDING));
    }
}
```
*ListingSubscriber.php*