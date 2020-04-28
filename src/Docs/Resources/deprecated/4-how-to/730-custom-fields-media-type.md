[titleEn]: <>(Use custom fields with media type)
[metaDescriptionEn]: <>(This HowTo will give an example how you can work with a custom field of type media.)
[hash]: <>(article:how_to_custom_field_media)

## Using custom fields of type media

After you added a custom field of type media, over the administration or via plugin, you can assign simply media objects to the different entities.
This is often used for products to show more information with images on the product detail page.
On the product detail page, under `page.product.translated.customFields.xxx`, `xxx` is the corresponding custom field, containing the UUID of the media.

However, it is not possible to display an image in the storefront with only this media ID. Therefore the function 'searchMedia' exists:

```
public function searchMedia(array $ids, Context $context): MediaCollection { ... }
```

This function reads out the corresponding media objects for the given IDs in order to continue working with them afterwards.
Here is an example with a custom field (`custom_sports_media_id`) on the product detail page :

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_media %}
    {# simplify ID access #}
    {% set sportsMediaId = page.product.translated.customFields.custom_sports_media_id %}

    {# fetch media as batch - optimized for performance #}
    {% set mediaCollection = searchMedia([sportsMediaId], context.context) %}

    {# extract single media object #}
    {% set sportsMedia = mediaCollection.get(sportsMediaId) %}

    {{ dump (sportsMedia) }}
{% endblock %}

```

### Avoid loops

Please note that this function performs a query against the database and should therefore not be used within a loop.
The function is already structured in a way that several IDs can be passed.
To read the media objects within the product listing we recommend the following procedure:

```twig
{% sw_extends '@Storefront/storefront/component/product/listing.html.twig' %}

{% block element_product_listing_col %}
    {# initial ID array #}
    {% set sportsMediaIds = [] %}

    {% for product in searchResult %}
        {# simplify ID access #}
        {% set sportsMediaId = product.translated.customFields.custom_sports_media_id %}

        {# merge IDs to a single array #}
        {% set sportsMediaIds = sportsMediaIds|merge([sportsMediaId]) %}
    {% endfor %}

    {# do a single fetch from database #}
    {% set mediaCollection = searchMedia(sportsMediaIds, context.context) %}

    {% for product in searchResult %}
        {# simplify ID access #}
        {% set sportsMediaId = product.translated.customFields.custom_sports_media_id %}

        {# get access to media of product #}
        {% set sportsMedia = mediaCollection.get(sportsMediaId) %}

        {{ dump(sportsMedia) }}
    {% endfor %}
{% endblock %}
```
