[titleEn]: <>(Use custom field labels in storefront)
[metaDescriptionEn]: <>(This HowTo will show you how to use custom field labels in storefront)
[hash]: <>(article:how_to_use_custom_field_labels_in_storefront)

## Overview

This guide will show you how to use custom field labels in the storefront. First of all, if you add a custom field via
API or administration, automatically snippets for all languages are created. The naming of the snippet is like the following
template: `customFields.` as prefix and then the name of the custom field. For example the name of the created custom field
is `my_test_field`, then the created snippet name is `customFields.my_test_field`. In the snippet settings in the administration
you could edit and translate the snippet.

## Storefront using

If you want to use the snippet in the storefront you have to extend a template file and add the snippet to it like this:
```twig
{# YourPlugin/Resources/views/storefront/page/product-detail/description.html.twig #}

{% sw_extends '@Storefront/storefront/page/product-detail/description.html.twig' %}

{% block page_product_detail_description_content_text %}
    {{ parent() }}
    
    {{ "customFields.my_test_field"|trans|sw_sanitize }}: {{ page.product.translated.customFields.my_test_field }}
{% endblock %}
```
