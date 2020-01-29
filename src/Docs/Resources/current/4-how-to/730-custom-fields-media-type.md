[titleEn]: <>(Use custom fields with media type)
[metaDescriptionEn]: <>(This HowTo will give an example how you can work with a custom field of type media.)

## Using custom fields of type media
After you added a custom field of type media, over the administration or via plugin, you can assign simply media objects to the different entities.
This is often used for products to show more information with pictures on the product detail page. On the product detail page, under 'page.product.translated.customFields.xxx', `xxx` is the corresponding custom field, the corresponding UUID of the media.

However, it is not possible to display an image in the storefront with this media id only. For this reason, the function 'searchMedia' exists:

```
public function searchMedia(array $ids, Context $context): MediaCollection { ... }
```

This function makes it possible to read out the corresponding media objects for exactly this case in order to continue working with them afterwards.
Here is an example of how this works with a custom field on the product detail page (`custom_sports_media_id` is the custom field):

```twig

{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_media %}
    {# simplify access to id #}
    {% set id = page.product.translated.customFields.custom_sports_media_id %}

    {# fetch media as batch - optimized for performance #}
    {% set media = searchMedia([id], context.context) %}

    {# extract single media object #}
    {% set sportsMedia = media.get(id) %}

    {{{ dump (sportsMedia) }}
{% endblock %}

```

## Avoid loops

Please note that this function performs a query against the database and should therefore not be used within a loop. The function is already structured in a way that several Ids can be passed.
To read the media objects within the product listing we recommend the following procedure:

```twig

{% sw_extends '@Storefront/storefront/component/product/listing.html.twig' %}

{% block element_product_listing_col %}
    {# initial id array #}
    {% set ids = [] %}

    {% for product in searchResult %}
        {% set id = product.translated.customFields.custom_sports_media_id %}

        {# merge ids to a single array #}
        {% set ids = ids|merge([id]) %}
    {% endfor %}

    {# do a single fetch from database #}
    {% set mediaItems = searchMedia(ids, context.context) %}

    {% for product in searchResult %}
        {# simplify id access #}
        {% set id = product.translated.customFields.custom_sports_media_id %}

        {# get access to media of product #}
        {% set media = mediaItems.get(id) %}

        {{{ dump(media)}
    {% endfor %}
{% endblock %}

```
