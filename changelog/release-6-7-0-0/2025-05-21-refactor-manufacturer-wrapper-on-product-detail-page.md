---
title: Changed manufacturer wrapper from `<a>` to `<div>` when link is missing
issue: #9615
author: Tam Dao
author_email: t.dao@shopware.com
author_github: @daothithientamm
---
# Storefront
* Changed the `element_manufacturer_logo_link` block in the `src/Storefront/Resources/views/storefront/element/cms-element-manufacturer-logo.html.twig` to render a `<div>` instead of an `<a>` tag when the manufacturer has no link, ensuring valid HTML structure and improved accessibility.
