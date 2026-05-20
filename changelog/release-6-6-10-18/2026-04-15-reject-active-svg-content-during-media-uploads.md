---
title: Reject active SVG content during media uploads
issue: GHSA-xvhc-gm7j-mhmc
---
# Core
* Added a new file content validation strategy in `\Shopware\Core\Content\Media\File\FileContentValidationStrategy` that runs tagged validators implementing `\Shopware\Core\Content\Media\File\AbstractFileContentValidator` during media persistence in `FileSaver`.
* Added `\Shopware\Core\Content\Media\File\SvgContentValidator` which validates uploaded SVGs against a strict passive allowlist. Active content such as scripts, event handler attributes, processing instructions, doctype/entity usage, animation, `foreignObject`, external `href`/`xlink:href` references, and URL-based references in attributes are rejected.
* Added new configuration keys `shopware.media.svg.allowed_elements`, `shopware.media.svg.allowed_attributes`, and `shopware.media.svg.allowed_reference_attributes` to widen the default passive SVG subset for installations that need it.
___
# Administration
* Changed `sw-media-upload-v2` so failed uploads now surface the backend error detail as a notification instead of only clearing the preview item.
___
# Upgrade Information
## Strict SVG validation on media uploads
SVG uploads in the media subsystem are now validated against a strict passive SVG allowlist before persistence.
Active content such as scripts, event handlers, processing instructions, external references, and URL-based references in attributes are rejected.

The accepted SVG subset can be adjusted on installation level via `shopware.media.svg.allowed_elements`, `shopware.media.svg.allowed_attributes`, and `shopware.media.svg.allowed_reference_attributes` in `shopware.yaml`.
