---
title: Improve keyboard UX and accessibility in top-bar navigation
issue: NEXT-26705
---
# Storefront
* Changed dropdown lists from `<div>` to `<ul><li>` inside `views/layout/header/actions/currency-widget.html.twig` and `layout/header/actions/language-widget.html.twig`.
* Deprecated custom CSS inside `scss/layout/_top-bar.scss`. Bootstrap variables inside `scss/skin/shopware/layout/_top-bar.scss` will be used instead to adjust the top-bar dropdown appearance.
* Deprecated `label.top-bar-list-label` and `input.top-bar-list-radio` elements inside `views/layout/header/actions/currency-widget.html.twig` and `layout/header/actions/language-widget.html.twig`. `<button>` elements will be used instead as dropdown items.
* Deprecated class `item-checked` inside `views/layout/header/actions/currency-widget.html.twig` and `views/layout/header/actions/language-widget.html.twig`. Bootstrap class `active` will be used instead.
* Deprecated block `layout_header_actions_currency_widget_form_items_element_input` inside `views/layout/header/actions/currency-widget.html.twig`, will be removed. Use parent block `layout_header_actions_currency_widget_form_items_element_label` instead.
___
# Upgrade Information

## Change Storefront language and currency dropdown items to buttons
The "top-bar" dropdown items inside `views/storefront/layout/header/top-bar.html.twig` will use `<button>` elements instead of hidden `<input type="radio">` when the `ACCESSIBILITY_TWEAKS` flag is `1`.
This will improve the keyboard navigation because the user can navigate through all options first before submitting the form.

Currently, every radio input change results in a form submit and thus in a page reload. Using button elements is also more aligned with Bootstraps dropdown HTML structure: [Bootstrap dropdown documentation](https://getbootstrap.com/docs/5.3/components/dropdowns/#menu-items)

___
# Next Major Version Changes

## Change Storefront language and currency dropdown items to buttons
The `.top-bar-list-item` elements inside the "top-bar" dropdown menus will contain `<button>` elements instead of a hidden `<input type="radio">` elements.

**Affected templates:**
* `Resources/views/storefront/layout/header/actions/language-widget.html.twig`
* `Resources/views/storefront/layout/header/actions/currency-widget.html.twig`

### Before:
```html
<ul class="top-bar-list dropdown-menu dropdown-menu-end">
    <li class="top-bar-list-item">
        <label class="top-bar-list-label" for="top-bar-01918f15b7e2711083e85ec52ac29411">
            <input class="top-bar-list-radio" id="top-bar-01918f15b7e2711083e85ec52ac29411" value="01918f15b7e2711083e85ec52ac29411" name="currencyId" type="radio">
            £ GBP
        </label>
    </li>
    <li class="top-bar-list-item">
        <label class="top-bar-list-label" for="top-bar-01918f15b7e2711083e85ec52ac29411">
            <input class="top-bar-list-radio" id="top-bar-01918f15b7e2711083e85ec52ac29411" value="01918f15b7e2711083e85ec52ac29411" name="currencyId" type="radio">
            $ USD
        </label>
    </li>
</ul>
```

### After:
```html
<ul class="top-bar-list dropdown-menu dropdown-menu-end">
    <li class="top-bar-list-item">
        <button class="dropdown-item" type="submit" name="currencyId" id="top-bar-01918f15b7e2711083e85ec52ac29411" value="01918f15b7e2711083e85ec52ac29411">
            <span aria-hidden="true" class="top-bar-list-item-currency-symbol">£</span>
            Pound
        </button>
    </li>
    <li class="top-bar-list-item">
        <button class="dropdown-item" type="submit" name="currencyId" id="top-bar-01918f15b7e2711083e85ec52ac29411" value="01918f15b7e2711083e85ec52ac29411">
            <span aria-hidden="true" class="top-bar-list-item-currency-symbol">$</span>
            US-Dollar
        </button>
    </li>
</ul>
```

If you are modifying the dropdown item, please adjust to the new HTML structure and consider the deprecation comments in the code. 
The example below shows `currency-widget.html.twig`. Inside `language-widget.html.twig` a similar structure can be found.

### Before:
```twig
{% sw_extends '@Storefront/storefront/layout/header/actions/currency-widget.html.twig' %}

{% block layout_header_actions_currency_widget_form_items_element_input %}
    <input type="radio">
    Special list-item override
{% endblock %}
```

### After:
```twig
{% sw_extends '@Storefront/storefront/layout/header/actions/currency-widget.html.twig' %}

{# The block `layout_header_actions_currency_widget_form_items_element_input` does no longer exist, use upper block `layout_header_actions_currency_widget_form_items_element_label` insted. #}
{% block layout_header_actions_currency_widget_form_items_element_label %}
    <button class="dropdown-item">
        Special list-item override
    </button>
{% endblock %}
```
