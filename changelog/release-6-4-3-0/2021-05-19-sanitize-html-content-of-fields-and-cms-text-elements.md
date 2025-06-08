---
title: Sanitize HTML contents of fields and CMS text elements
issue: NEXT-15172
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed dependency of `ezyang/htmlpurifier` from `Storefront` to `Core`
* Added `HtmlSanitizer` service to framework utils
* Changed former `htmlPurifier` config from `Storefront` to `Core` as `shopware.html_sanitizer`
* Added `$sanitize` constructor parameter to `AllowHtml` field flag to specify whether html content should be sanitized as per `HtmlSanitizer`
* Changed hanlding of text CMS element contents in `TextCmsElementResolver` to sanitize HTML
___
# Administration
* Added `sanitizeInput` and `sanitizeFieldname` property to `SwTextEditor` and `SwCodeEditor` component
* Added `userInputSanitizeService` with `sanitizeInput({ html, field })` method to receive a preview of backend sanitization
* Changed `SwCodeEditor` `onBlur()` behavior to use `userInputSanitizeService` if `sanitizeInput` property is set
* Added `sanitizeEditorInput(value)` function to `SwCodeEditor` for previewing sanitized content to the user
___
# Storefront
* Changed `SwSanitizeTwigFilter` to use `HtmlSanitizer`
