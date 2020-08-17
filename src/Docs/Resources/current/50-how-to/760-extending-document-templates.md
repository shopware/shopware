[titleEn]: <>(Extending document templates)
[metaDescriptionEn]: <>(This HowTo will show you how to customize document templates)
[hash]: <>(article:how_to_extend_document_templates)

## Overview

This guide will show you how to extend document templates. The document templates are located in the directory `/platform/src/Core/Framework/Resources/views/documents`.

There are two types of templates inside this directory:
1. Templates which are stored inside the `views/documents` directory
2. Templates which are stored inside the `views/documents/includes` directory

The first type of template, which are located in the root directory (`views/documents`), can be extended with the `sw_extends` pattern, like all other templates in shopware.
Each document type - invoice, storno, delivery_note and credit_note - has their own custom template, in this directory, with their specific overwrites. All of this templates
are an extension of the `base.html.twig` and follow the same structure and logic.
If you want to make a global extension, which should be available in all document types, you can simple add a custom template which extends the `base.html.twig`.
To make type specific changes, you can simply create a template with the corresponding name, for example `invoice.html.twig`, and add the right `sw_extends`.
 
The second type of template, which are located in the `includes` directory, are templates which are included with the twig `block()` function. This type of templates
can not be extended via `sw_extends` pattern. Instead of using the `sw_extends` pattern here, you can overwrite each block of this template inside your `base.html.twig` extension (or the type specific template).

Lets try an example. You want to overwrite the block `{% block document_header %}` which is placed inside `includes/logo.html.twig`. The overwrite should be available in all types of documents (invoice, storno, ...), so you can create a custom template
which extends the `base.html.twig` and simply overwrite this block:

```twig

{# file: custom/plugins/example-plugin/src/Resources/views/documents/base.html.twig #}

{% sw_extends '@Framework/documents/base.html.twig' %}

{% block document_header %}
    <h1>Logo replacement</h1>
{% endblock %}

``` 

Lets try another example. You want to overwrite the `{% block document_line_item_table_shipping_label %}` which is placed inside `includes/shipping_costs.html.twig`. This overwrite should only be available for invoice documents, so you can create custom template
which extends the `invoice.html.twig` and simply overwrite this block:

```twig
{# file: custom/plugins/example-plugin/src/Resources/views/documents/invoice.html.twig #}

{% sw_extends '@Framework/documents/invoice.html.twig' %}

{% block document_line_item_table_shipping_label %}
    <td class="line-item-breakable">
        Invoice shipping method label
    </td>
{% endblock %}
```

When you added a template extension for the `invoice.html.twig` and additionally for the `base.html.twig`, both extension will be rendered when a document of type `invoice` will be generated.
