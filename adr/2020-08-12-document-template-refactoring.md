---
title: Document template refactoring
date: 2020-08-12
area: customer-order
tags: [document, template, twig]
---

## Context
Our document templates did not support nested line items. To make this possible, we had to split the document templates into smaller templates.
This was necessary, because the logic of how the document is rendered has changed a lot. Previously it worked with a simple loop over the line items, now they are rendered recursively.

In the previous implementation was only one `base.html.twig`, which contained all the template logic.
Depending on the document type, there were different templates with smaller overrides: `invoice.html.twig`, `delivery_note.html.twig`.
In these specific templates only one or two blocks for headline or other addresses were overwritten.

After refactoring, however, this was no longer possible because the different blocks were moved to different files.
There are now two ways how we make the overwrite possible again: 

1. set a block around each include and then overwrite the includes in the corresponding templates and include a separate template

* **base.html.twig**
```twig
{% block include_header %}
    {% sw_include '@Framework/documents/header.html.twig' %}
{% endblock %}
```

* **invoice.html.twig**
```twig
{% sw_extends '@Framework/documents/base.html.twig' %}

{% block include_header %}
    {% sw_include '@Framework/documents/invoice_header.html.twig' %}
{% endblock %}
```

**Disadvantage**: 
To exchange a block (depending on the document type), you must first overwrite `base.html.twig`. 
Then you have to overwrite the corresponding `include` to include another file there `invoice_header.html.twig`.
The templates are only included in several places, so for some blocks you have to overwrite several includes.
In addition to this, serious errors can occur here if several developers want to overwrite an include. Inheritance would not work because one plugin has its `invoice_footer.html.twig` included and the other plugin has another.

**Advantage**:
A developer can still overwrite any template defined by us via `sw_extends`

2. We use the `use` syntax of Twig, which allows to overwrite the blocks of included files

```twig
{% block include_header %}
    {% sw_include '@Framework/documents/header.html.twig' %}
{% endblock %}
```

* `invoice.html.twig`
```twig
{% sw_extends '@Framework/documents/base.html.twig' %}

{% block headline %}
    <h1>invoice</h1>
{% endblock %}
```

**Disadvantage** 
Templates that are rendered per `use` cannot be inherited. This logic is different from the previous storefront template logic and must be clearly documented.

**Advantage** 
A developer can simply overwrite the `base.html.twig` and directly extend and restructure all blocks. This would even correspond to the current behavior.
Furthermore, the developer can make document type specific customizations in a simple `invoice.html.twig` without having to overwrite all the `includes`.
If several developers want to overwrite a block, they can't get in each other's way through the different includes.

## Decision
To keep the extensibility of the document templates simple, we will work with the `use` and `block` pattern from Twig:

* https://twig.symfony.com/doc/3.x/tags/use.html
* https://twig.symfony.com/doc/3.x/functions/block.html

```
{% use '@Framework/documents/includes/logo.html.twig' %}

{{ block('logo') }}
```

## Consequences
* Templates from the folder `/platform/src/Core/Framework/Resources/views/documents/includes` cannot be extended by the developers via `sw_extends`
* We wrote a new how-to guide, which explains the new behavior
* We have placed a note/comment in the corresponding templates which points out the new behavior.
