---
title: Hide and show CMS content
date: 2022-10-17
area: content
tags: [cms, storefront, admin]
---

## Context
Many merchants reached out to us, that the possibility to customize content per device is important for them. Therefore, we would like to provide a solution to allow merchants to hide and show cms sections or blocks per devices.

## Decision
Blocks and sections should be displayed or hidden per viewports, so we decided to do this on the client side via CSS media queries. We won't do this on the server side, because we don't want a full-page reload in order to hide/show blocks or sections. We also won't do an ajax call because for each block, and section this could cause too many requests on one page.

### Cms section and Cms block config
Merchants can use the cms sections and blocks to customize their storefront.

### Problems:
- The shop merchant wants to configure sections or blocks to display them depending on the respective device.

### Solution:
- We want more flexibility in the future by adding more options to configure the visibility of blocks and sections. That is why we will add visibility as a JSON column to `cms_section`, and `cms_block` table to save config for that section or block.

**Example in pseudocode:**

```json
{
    'mobile': true,
    'tablet': true,
    'desktop': true
}
```

### Administration
Blocks and sections are visible on all viewports by default. In the administration, we add a new visibility section under block and section settings to allow merchants to hide or show blocks or sections by device.

**Example in pseudocode:**

```html
<sw-checkbox-field
    v-model="visibility.mobile"
    class=sw-cms-visibility-config__checkbox-input
    :label="$tc('sw-cms.sidebar.contentMenu.visibilityMobile')"
/>

<sw-checkbox-field
    v-model="visibility.tablet"
    class="sw-cms-visibility-config__checkbox-input"
    :label="$tc('sw-cms.sidebar.contentMenu.visibilityTablet')"
/>

<sw-checkbox-field
    v-model="visibility.desktop"
    class="sw-cms-visibility-config__checkbox-input"
    :label="$tc('sw-cms.sidebar.contentMenu.visibilityDesktop')"
/>
```

### Storefront
Based on the settings set in the administration, in the storefront we will add css classes in `src/Storefront/Resources/views/storefront/section/cms-section-default.html.twig` & `src/Storefront/Resources/views/storefront/section/cms-section-block-container.html.twig` to hide blocks or sections using CSS media queries.

**Example in pseudocode:**

```html
{% if block.visibility is null %}
    {% set block = {
        visibility: {
            mobile: true,
            tablet: true,
            desktop: true
        }
    } %}
{% endif %}

{% if not block.visibility.mobile %}
    {% set blockClasses = ['hidden-mobile']|merge(blockClasses) %}
{% endif %}
{% if not block.visibility.tablet %}
    {% set blockClasses = ['hidden-tablet']|merge(blockClasses) %}
{% endif %}
{% if not block.visibility.desktop %}
    {% set blockClasses = ['hidden-desktop']|merge(blockClasses) %}
{% endif %}
```
