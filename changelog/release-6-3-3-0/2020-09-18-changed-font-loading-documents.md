---
title:              Fixed document generation with special utf-8 characters
issue:              NEXT-8755
author:             Patrick Stahl
author_email:       p.stahl@shopware.com
author_github:      @PaddyS
---
# Core
* Changed the loading of fonts when generating documents by using a `<link>` tag to load a font instead of using `url()` in CSS
* Added new twig block `document_font_links` in `platform/src/Core/Framework/Resources/views/documents/base.html.twig`
* Removed `@font-face` from `style_base_landscape.css.twig` and `style_base_portrait.css.twig`, since it's now loaded using a link tag due to issues with domPDF
