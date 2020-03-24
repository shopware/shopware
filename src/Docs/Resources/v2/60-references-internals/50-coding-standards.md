[titleEn]: <>(Coding standards)
[hash]: <>(article:references_coding_standards)

## Core

## Storefront

### Twig

* Block names always snake_case
* Block names according to directory structure
* Html class names in Bootstrap code style
* Always indent after a block

#### Indent after block:
Bad:
```
{% block layout_header %}
<header class="main-header">
    <div class="main-header-inner"></div>
</header>
{% endblock %}
```
Good:
```
{% block layout_header %}
    <header class="main-header">
        <div class="main-header-inner"></div>
    </header>
{% endblock %}
```

### JavaScript

### SCSS

## Administration

## Vue / JavaScript

## Twig templates
