---
title: Allow snippet value to include bootstrap 5 data attributes
issue: NEXT-26073
---
# Core
* Added a new `bootstrap` set for parameter `shopware.html_sanitizer.sets` to allow bootstrap 5 data attributes
* Added `custom_attributes` option in a html_sanitizer set to allow adding custom attributes to `HTMLPurifier_Config`
* Changed method `\Shopware\Core\Framework\Util\HtmlSanitizer::sanitize` to add custom attributes to `HTMLPurifier_Config` before purify string 
* Added `bootstrap` set for `snippet.value` field in parameter `shopware.html_sanitizer.fields`
