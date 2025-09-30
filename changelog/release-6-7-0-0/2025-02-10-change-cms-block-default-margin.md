---
title: Changed the default CMS block margin for default listing pages to be aligned
issue: NEXT-39528
---
# Core
* Changed the left and right margin of CMS blocks within the default listing pages to `null` so the layout is aligned with the rest of the page.
___
# Storefront
* Added new inner wrapping `<div>` within `.cms-section-sidebar` in `storefront/section/cms-section-sidebar.html.twig` and moved the Bootstrap `.row` class to the new inner `<div>` element. This makes the sidebar layout compliant with Bootstrap native grid system.
* Added new block `page_content_section_sidebar_row` in `storefront/section/cms-section-sidebar.html.twig` for the new inner row.
* Added the gutter size variable `--#{$prefix}gutter-x` to `.cms-section-sidebar > row` within `storefront/src/scss/component/_cms-sections.scss` to control the gutter size between sidebar and listing. It is set to `60px` to maintain the original gap size.
* Added styles for the CMS layout option class `.full-width` by extending the native Bootstrap class `.container-fluid` in `storefront/src/scss/component/_cms-sections.scss` to use regular Bootstrap styling for the container settings.
___
# Administration
* Changed the general default value of CMS blocks for left and right margin to `null` so they don't cause layout issues by default.
___
# Upgrade Information

## CMS block margins

### Default settings for blocks within the Administration
We updated the default values for the left and right margin of blocks within the Shopping Experience module. Previously, the setting within the "Layout" tab of each block was set to `20px` which led to an additional outer margin of the content that made the inner content of the page to be not aligned with the rest of the page layout. The top and bottom margin default values will stay at `20px` so blocks have a white space between each other. You can always change these settings in the "Layout" tab of your block settings. Existing pages are not affected. But, if you add new blocks to any new or existing page the new default settings will be used.

### Block margins of default templates
The locked default templates for listing pages in Shopware were also updated, so that the standard blocks don't have that margin. If you duplicated the standard templates to do your own customization, your pages won't be affected.

### Bootstrap grid in sidebar sections
We also tweaked the template of CMS sections with a sidebar to make it compliant with the Bootstrap grid system and to properly handle the margin settings. You can control the space between the sidebar and the content by simply setting the gutter variable on `.cms-section-sidebar > .row`. The current gap is `60px` wide.

**Example:**
```SCSS
.cms-section-sidebar > .row {
    --#{$prefix}gutter-x: 80px;
}
```

