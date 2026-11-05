---
name: shopware-experience-studio-icons
description: Create or refine SVG illustrations for the Shopware Experience Studio element and preset picker, matching its established frame, soft shadow, palette, and local/Meteor icon selection. Use for new content thumbnails or visual consistency updates, not generic icon or logo work.
license: MIT
---

# Experience Studio SVG Icons

Create vector illustrations that resemble the element's rendered content and match the existing Experience Studio icon family.

## Before Drawing

- Read [the visual specification](references/visual-specification.md) for the exact dimensions, frame, shadow, palette, and content bounds.
- Start from [the SVG template](assets/element-icon-template.svg).
  Preserve its frame and definitions; draw the element-specific content inside the inner group.
- Inspect the closest existing illustrations in `src/Administration/Resources/app/administration/static/img/experience-studio/element-icons/`.
  Useful examples are `media-gallery.svg`, `product-slider.svg`, `product-review-summary.svg`, and `grid-container.svg`.
- Read the element's YAML declaration or rendered component to identify its distinguishing content.
  Represent that content rather than inventing unrelated decoration.

## Draw and Integrate

- Author editable SVG paths and shapes directly.
  Keep the common light border and soft shadow on every illustration, and omit browser toolbars.
  Put labels outside the SVG through the existing picker UI.
- Keep the SVG independent of fonts and external resources.
  Outline any illustrative numerals or symbols into paths.
- Save the asset as a descriptive kebab-case `.svg` in the existing icon directory.
- Select the asset by its filename without `.svg`, through `meta.illustration: "media-image"` in an element definition or top-level `illustration: "preset-media-and-text"` in a preset.
  Set `icon` separately to the desired Meteor name, such as `regular-image`.
  The picker resolves the SVG directly from the icon directory; no mapping entry or prefix is required.
  The tree uses `icon`, and the picker falls back to it if the illustration cannot load.
  For an artwork-only change, leave registration and picker behavior alone.

## Validate and Deliver

- Parse every changed SVG as XML and check its frame, gradients, shadow, and local definition references against the template.
- Render the SVGs individually and together as a labelled contact sheet.
  Inspect them at the native `200 × 128` size and the picker's `92px` illustration height for clipping, contrast, consistent framing, and recognisable content.
- Run the relevant Shopware Administration checks when changing TypeScript, templates, or styling; follow `shopware-admin-js` for those changes.
  Artwork-only changes need SVG validation and visual review, without new PHP integration tests.
- Return a preview and show the exact `icon` and `illustration` YAML values for new icons.
