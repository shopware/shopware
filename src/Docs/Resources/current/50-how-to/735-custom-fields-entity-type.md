[titleEn]: <>(Use custom fields with entity select)
[metaDescriptionEn]: <>(This HowTo will give an example how you can work with a custom field of entity select type to resolve the associated data.)
[hash]: <>(article:how_to_custom_field_entity_select)

## Using custom fields of type entity select

In this how-to, we will display the category names of categories on the product detail page, that were previously added to a product via custom fields of type entity select.

With custom fields of type entity select you can add custom fields to entities which hold related entities themselves.

*Note: the custom field does only save the related entities IDs. There is no foreign key relation and no cascade operations. If you delete the related entity, the custom field will still hold the ID of the deleted entity.*

For the purpose of this how-to, let us assume you have added a custom field of type entity select.
The custom field is assigned to products, and you have assigned some related categories to it.

### Replace custom field IDs with actual entities

As the custom field will only hold the related entity IDs, you will have to fetch the data on your own, if you want the entities instead.
In this example we want to fetch the actual entites of all assigned categories whenever the product page is loaded.

For that we create a custom subscriber, get the custom field entity IDs and fetch the corresponding entities via the DAL: 

```php
<?php declare(strict_types=1);

namespace MyCustomFieldEntityTypePlugin\Storefront\Event;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MyCustomProductPageLoaderSubscriber implements EventSubscriberInterface
{
    private const CUSTOM_FIELD_TECHNICAL_NAME = 'custom_test_category';

    /** @var EntityRepositoryInterface */
    private $categoryRepository;

    // loaded via Dependency Injection 
    // remember to register the subscriber in you services.xml !
    public function __construct(EntityRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public static function getSubscribedEvents()
    {
        // subscribe to the event
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        // the product is inside the page object
        $product = $event->getPage()->getProduct();

        // get the custom fields
        $customFields = $product->getCustomFields();

        // we can skip, if no custom fields are assigned
        if (!$customFields) {
            return;
        }

        // we can skip, if product not has our specific custom field
        if (!\array_key_exists(self::CUSTOM_FIELD_TECHNICAL_NAME, $customFields)) {
            return;
        }

        // category ID(s) stored in the custom field
        $categories = $customFields[self::CUSTOM_FIELD_TECHNICAL_NAME];

        // search for categories with given IDs
        // be aware that previously removed entities will not be fetched from the DAL
        $categoryEntities = $this->categoryRepository
            ->search(new Criteria($categories), $event->getContext())
            ->getEntities();

        // store the actual entities in the custom fields
        $customFields['my_custom_categories'] = $categoryEntities;

        // here you will have all the categories attached to the product's custom field
        $product->setCustomFields($customFields);
    }
}
```

*Note: Do not forget to register your subscriber to the service container in your plugin's services.xml:* 
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="MyCustomFieldEntityTypePlugin\Storefront\Event\MyCustomProductPageLoaderSubscriber"
                 class="MyCustomFieldEntityTypePlugin\Storefront\Event\MyCustomProductPageLoaderSubscriber">
            <argument type="service" id="category.repository" />
            <tag name="kernel.event_subscriber" />
        </service>
    </services>
</container>
```

### Show the category name on the product detail page

Now that we have transposed the IDs into actual entities, we can use them in a custom template.

For that we extend the default shopware product detail buy-box widget by creating `<plugin>/src/Resources/views/storefront/page/product-detail/buy-widget.html.twig`:

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/buy-widget.html.twig' %}

{# override the page_product_detail_buy_container block #}
{% block page_product_detail_buy_container %}

    {# prepend the parent's content #}
    {{ parent() }}

    {# create custom block -> best practice #}
    {% block page_product_detail_buy_container_categories %}
        <ul class="my-custom-field-category-group">

            {# iterate through the custom field categories #}
            {# page.product.customFields.custom_test_category now holds actual entities thanks to our subscriber !#}
            {% for category in page.product.customFields.my_custom_categories %}
                <li class="my-custom-field-category-item">

                    {# print the category's name #}
                    {{ category.name }}
                </li>
            {% endfor %}
        </ul>
    {% endblock %}
{% endblock %}
``
