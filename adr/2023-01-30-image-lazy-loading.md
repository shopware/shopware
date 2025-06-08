---
title: Add native lazy loading for images to the storefront
date: 2023-01-30
area: storefront
tags: [image, lazy-loading, storefront]
---

## Context

* Currently, the images/thumbnails inside the Storefront are not making use of any lazy loading mechanism. 
* Without a third-party extension which includes something like "lazysizes" it is not possible to get lazy loading images.
* Native lazy loading of images is available in current browsers, see: https://caniuse.com/?search=loading

## Decision

* We want to make use of native lazy loading for images in the Storefront without adding additional JavaScript logic.

## Consequences

* We pass a new attribute `loading="lazy"` to several usages of the thumbnail component `Resources/views/storefront/utilities/thumbnail.html.twig`. This enables native lazy loading.
* By default, the thumbnail component uses `loading="eager"` which is the default behaviour and has the same effect as not setting the attribute.
* The default is not `lazy` in order to avoid unexpected behaviour for extensions which might have added the thumbnail component while using a JavaScript solution for lazy loading.
* We add native lazy loading in appropriate areas to reduce the initial network load:
    * Main menu flyout: Category preview images will only load when flyout is being opened.
    * Product boxes: Product images will only load when they appear in the viewport inside the listing. This also affects product sliders with horizontal scrolling, e.g. cross-selling.
    * CMS image elements: CMS layouts will only load images which appear in the viewport (e.g., when scrolling down the page).
    * Line item images: Product images in line items (e.g., cart page) will only load when they appear in the viewport.

### Why don't we just add `loading="lazy"` everywhere?

* Even though this would technically work, there are a few pitfalls that need to be considered. 
* For example, it is not recommended to add lazy loading to images which are very likely inside the initial viewport when loading the page aka "above-the-fold". Further reading: https://web.dev/browser-level-lazy-loading-for-cmss/#avoid-lazy-loading-above-the-fold-elements
* For a system like shopware, where the content is almost entirely dynamic, it is not easy to determine where a generic image component will be rendered. It could have any position on any CMS page.
* Even "guesses" like "only add lazy loading after the 8th product in a listing" can be invalid as soon as a monitor is in portrait mode or the viewport changes to mobile.
* Therefore, we live with the small drawback that, e.g., all product boxes have lazy loading. Some of them will appear "above-the-fold". However, we still have the benefit of loading images later when scrolling down a page or scrolling in product sliders. 
* Implementing a JavaScript solution for this would contradict the usage of native lazy loading.

#### Areas without loading="lazy"`

* Image gallery on product detail page: This is very likely "above-the-fold" and the gallery already uses JavaScript lazy loading for the image zoom as well.
* Image sliders (CMS): When sliding to the next image, the lazy loading can lead to a bad user experience because the image can appear too late.

#### How to activate lazy loading?

When using the thumbnail component, pass the `loading` attribute with value `lazy`:

```diff
{% sw_thumbnails 'my-thumbnail' with {
    media: category.media,
    attributes: {
        'class': 'my-css-class'
+        'loading': 'lazy'
    }
} %}
```
