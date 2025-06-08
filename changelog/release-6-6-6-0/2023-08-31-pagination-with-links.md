---
title: Pagination with links
issue: NEXT-28114
---
# Storefront
* Added new optional variable `href` (default: `true`) to template `Resources/views/storefront/component/pagination.html.twig` to provide a relative url for the pagination links `<a href="?p=2">`.
* Added new option `ariaHidden` to `{% sw_icon 'icon-name' style { ariaHidden: true } %}` to hide an SVG icon from the screen reader.
* Added new parameter `focusOptons` to `window.focusHandler.resumeFocusState` and `window.focusHandler.setFocus`.
* Changed `<input type="radio">` and `<label>` elements to `<a href="#">` in `Resources/views/storefront/component/pagination.html.twig` behind feature flag `ACCESSIBILITY_TWEAKS`.
* Added new `FormAjaxPaginationPlugin` to control new link pagination in forms where the page info is sent with post request.
* Deprecated the following blocks in `Resources/views/storefront/component/pagination.html.twig`, use documented alternatives instead.
    * Deprecated `component_pagination_first_input`. Block and radio input be removed. Use new anchor element in block `component_pagination_first_link_element` instead.
    * Deprecated `component_pagination_first_label`. Label element will be replaced by anchor element. Use block `component_pagination_first_link_element` instead.
    * Deprecated `component_pagination_first_link`. Label element will be replaced by anchor element. Use block `component_pagination_first_link_icon` instead.
    * Deprecated `component_pagination_prev_input`. Block and radio input will be removed. Use new anchor element in block `component_pagination_prev_link_element` instead.
    * Deprecated `component_pagination_prev_label`. Label element will be replaced by anchor element. Use block `component_pagination_prev_link_element` instead.
    * Deprecated `component_pagination_prev_link`. Label element will be replaced by anchor element. Use block `component_pagination_prev_link_element` instead.
    * Deprecated `component_pagination_prev_icon`.  Label element will be replaced by anchor element. Use block `component_pagination_prev_link_icon` instead.
    * Deprecated `component_pagination_item_input`. Block and radio input will be removed. Use new anchor element in block `component_pagination_item_link_element` instead.
    * Deprecated `component_pagination_item_label`. Label element will be replaced by anchor element. Use block `component_pagination_item_link_element` instead.
    * Deprecated `component_pagination_item_link`. Label element will be replaced by anchor element. Use block `component_pagination_item_link_element` instead.
    * Deprecated `component_pagination_item_text`. Label element will be replaced by anchor element. Use block `component_pagination_item_link_text` instead.
    * Deprecated `component_pagination_next_input`. Block and radio input will be removed. Use new anchor element in block `component_pagination_next_link_element` instead.
    * Deprecated `component_pagination_next_label`. Label element will be replaced by anchor element. Use block `component_pagination_next_link_element` instead.
    * Deprecated `component_pagination_next_link`. Label element will be replaced by anchor element. Use block `component_pagination_next_link_element` instead.
    * Deprecated `component_pagination_next_icon`. Label element will be replaced by anchor element. Use block `component_pagination_next_link_icon` instead.
    * Deprecated `component_pagination_last_input`. Block and radio input will be removed. Use new anchor element in block `component_pagination_last_link_element` instead.
    * Deprecated `component_pagination_last_label`. Label element will be replaced by anchor element. Use block `component_pagination_last_link_element` instead.
    * Deprecated `component_pagination_last_link`. Label element will be replaced by anchor element. Use block `component_pagination_last_link_element` instead.
    * Deprecated `component_pagination_last_icon`. Label element will be replaced by anchor element. Use block `component_pagination_last_link_icon` instead.
* Deprecated `change` event listeners in `ListingPaginationPlugin._registerButtonEvents`. `click` listeners will be used instead on the anchor element. The radio input element will be removed.
* Deprecated retrieving the `this.tempValue` from `event.target.value` in `ListingPaginationPlugin.onChangePage`. The data attribute `data-page` (`event.currentTarget.dataset.page`) will be used instead.
* Deprecated pagination item selector `.pagination input[type=radio]` in `ListingPaginationPlugin`. `.pagination .page-link` will be used instead. The radio input element will be removed.
___
# Upgrade Information

## Rework Storefront pagination to use anchor links and improve accessibility
We want to change the Storefront pagination component (`Resources/views/storefront/component/pagination.html.twig`) to use anchor links `<a href="#"></a>` instead of radio inputs with styled labels.
This will improve the accessibility and keyboard operation, as well as the HTML semantics. The pagination with anchor links will also be more aligned with the Bootstrap pagination semantics.

To avoid breaking changes, the updated pagination can only be activated by setting the `ACCESSIBILITY_TWEAKS` feature flag to `1`. With the next major version `v6.7.0` the updated pagination will become the default.
The pagination will also be more simple because the hidden radio input and the label are no longer there. We only use a single anchor link element instead.

### Pagination item markup before:
```html
<li class="page-item">
  <input type="radio" name="p" id="p2" value="2" class="d-none" title="pagination">
  <label class="page-link" for="p2">2</label>
</li>
```

### Pagination item markup after:
```html
<li class="page-item">
    <a href="?p=2" class="page-link" data-page="2" data-focus-id="2">2</a>
</li>
```

## New `ariaHidden` option for `sw_icon`
When rendering an icon using the `{% sw_icon %}` function, it is now possible to pass an `ariaHidden` option to hide the icon from the screen reader.
This can be helpful if the icon is only decorative or the purpose is already explained in a parent elements aria-label or title.

```diff
{# Twig implementation #}
<a href="#" aria-label="Go to first page">
-    {% sw_icon 'arrow-medium-double-left' style { pack: 'solid' } %}
+    {% sw_icon 'arrow-medium-double-left' style { pack: 'solid', ariaHidden: true } %}
</a>

<!-- HTML result -->
<a href="#" aria-label="Go to first page">
-    <span class="icon icon-arrow-medium-double-left icon-fluid"><svg></svg></span>
+    <span aria-hidden="true" class="icon icon-arrow-medium-double-left icon-fluid"><svg></svg></span>
</a>
```
___
# Next Major Version Changes

## Storefront pagination is using anchor links instead of radio inputs
The storefront pagination component (`Resources/views/storefront/component/pagination.html.twig`) is no longer using radio inputs with styled labels. Anchor links are used instead.
If you are modifying the `<label>` inside the pagination template, you need to change the markup to `<a>` instead. Please use one of the documented twig block alternatives inside `pagination.html.twig`.
The hidden radio input will no longer be in the HTML. The current page value will be retrieved by the `data-page` attribute instead of the radio inputs value.

### Before:
```twig
{% sw_extends '@Storefront/storefront/component/pagination.html.twig '%}

{% block component_pagination_first_input %}
    <input type="radio"
           {% if currentPage == 1 %}disabled="disabled"{% endif %}
           name="p"
           id="p-first{{ paginationSuffix }}"
           value="1"
           class="d-none some-special-class"
           title="pagination">
{% endblock %}

{% block component_pagination_first_label %}
    <label class="page-link some-special-class" for="p-first{{ paginationSuffix }}">
        {# Using text instead of icon and add some special CSS class #}
        First
    </label>
{% endblock %}
```

### After:
```twig
{% sw_extends '@Storefront/storefront/component/pagination.html.twig '%}

{# All information that was previously on the radio input, is now also on the anchor link. The id attribute is longer needed. The "disabled" state is now controlled via the parent `<li>` and tabindex. #}
{% block component_pagination_first_link_element %}
    <a href="{{ href ? '?p=1' ~ searchQuery : '#' }}" 
       class="page-link some-special-class"
       data-page="1"
       aria-label="{{ 'general.first'|trans|striptags }}" 
       data-focus-id="first"
       {% if currentPage == 1 %} tabindex="-1" aria-disabled="true"{% endif %}>
        {# Using text instead of icon and add some special CSS class #}
        First
    </a>
{% endblock %}
```
