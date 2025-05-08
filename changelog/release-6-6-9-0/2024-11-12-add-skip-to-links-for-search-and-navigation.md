---
title: Add skip to links for search and navigation
issue: NEXT-35928
---
# Storefront
* Added new include template `@Storefront/storefront/component/skip-to-content.html.twig` for "skip-to" accessibility links inside `base_body_skip_to_content`.
___
# Upgrade Information

## New skip to content links
The "Skip to content" link for accessibility inside `@Storefront/storefront/base.html.twig` is now inside a separate include template `@Storefront/storefront/component/skip-to-content.html.twig`.
The new template also has additional links to skip directly to the search field and main navigation. The links can be enabled or disabled by passing boolean variables. By default, only "Skip to main content" is shown:

```twig
{% sw_include '@Storefront/storefront/component/skip-to-content.html.twig' with {
    skipToContent: true,
    skipToSearch: true,
    skipToMainNav: true
} %}
```