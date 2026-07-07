---
title: SVG validator accepts more passive extension assets
issue: #17617
---
# Core
* Changed SVG media validation to accept additional passive SVG elements, attributes, metadata, inline fonts, safe animation attributes, known editor namespaces, public SVG doctypes without internal subsets, and embedded raster image data URIs. This allows more SVG assets shipped by extensions and themes to pass validation while still rejecting active content such as external references, processing instructions outside scoped metadata, `foreignObject`, and entity definitions.
