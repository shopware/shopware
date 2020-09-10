[titleEn]: <>(sw_custom_field_labels)
[metaDescriptionEn]: <>(sw_custom_field_labels - Twig filter)
[hash]: <>(article:sw_custom_field_labels_twig_filter)

If you want to use the labels of the custom fields of e.g. a product in the storefront, we provide a twig filter for this.
This filter is easy to use and needs an array with the custom field names as a parameter.
The filter returns all given labels as an array with the custom field names as key and the translated labels as value.

Example:

Example:
```twig
{# YourPlugin/Resources/views/storefront/page/product-detail/description.html.twig #}

{% sw_extends '@Storefront/storefront/page/product-detail/description.html.twig' %}

{% block page_product_detail_description_content_text %}
    {{ parent() }}
    
    {%  set labels = page.product.customFields| keys | sw_custom_field_labels %}
    {% for key, label in labels %}
        <span>{{ label }}: </span> <span>{{ page.product.customFields[key] }}</span>
    {% endfor %}
{% endblock %}
```
