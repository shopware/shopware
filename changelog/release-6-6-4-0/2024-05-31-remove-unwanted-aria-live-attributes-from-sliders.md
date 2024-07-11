---
title: Remove unwanted aria-live attributes from sliders
issue: NEXT-33675
---
# Storefront
* Added NPM dependency `patch-package` to allow patches of NPM packages.
___
# Upgrade Information
## Added new `ariaLive` option to Storefront sliders
By default, all Storefront sliders/carousels (`GallerySliderPlugin`, `BaseSliderPlugin`, `ProductSliderPlugin`) are adding an `aria-live` region to announce slider updates to a screen reader.

In some cases this can worsen the accessibility, for example when a slider uses "auto slide" functionality. With automatic slide the slider updates can disturb the reading of other contents on the page.

You can now deactivate the `aria-live` region on the slider plugins with the new option `ariaLive` (default: `true`).

Example for `GallerySliderPlugin` (Also works for `BaseSliderPlugin` and `ProductSliderPlugin`)
```diff
{% set gallerySliderOptions = {
    slider: {
+        ariaLive: false,
        autoHeight: false,
    },
    thumbnailSlider: {
+        ariaLive: false,
        controls: true,
        responsive: {}
    }
} %}

<div data-gallery-slider-options='{{ gallerySliderOptions|json_encode }}'>
```

When `ariaLive` is `false` it will omit the `aria-live` region in the generated `tiny-slider` HTML code:
```diff
<div class="tns-outer" id="tns3-ow">
-    <div class="tns-liveregion tns-visually-hidden" aria-live="polite" aria-atomic="true">
-        slide <span class="current">2</span> of 6
-    </div>
    <div id="tns3-mw" class="tns-ovh">
        <!-- Slider contents -->
    </div>
</div>
```
