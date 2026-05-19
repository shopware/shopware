---
title: Replace breadcrumb separator by the bootstrap default solution
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Deprecated twig block `layout_breadcrumb_placeholder` from the template `@Storefront/storefront/layout/breadcrumb.html.twig` and replaced the breadcrumb separator by the bootstrap default implementation, adjust the `breadcrumbDivider` variable to use a custom separator
* Deprecated the twig variable `breadcrumbKeys` as it is not needed anymore
* Added the attribute `aria-hidden="true"` to the `.breadcrumb-placeholder` for accessibility
___
# Upgrade Information
## Breadcrumb separator using Bootstrap default

The breadcrumb separator is now using the bootstrap default, i.e. the CSS variable `--bs-breadcrumb-divider`, which is set on the corresponding breadcrumb `nav`-element: https://getbootstrap.com/docs/5.3/components/breadcrumb/#dividers

Therefore the block `layout_breadcrumb_placeholder` has been deprecated and will be removed and the separator can be set using the twig variable `breadcrumbDivider`, i.e.
```twig
{% block layout_breadcrumb_container %}
    {% with {breadcrumbDivider: 'url(data:image/svg+xml,' ~ source('@Storefront/assets/icon/my-custom-separator.svg')|url_encode ~ ')'} %}
        {{ parent() }}
    {% endwith %}
{% endblock %}
```
