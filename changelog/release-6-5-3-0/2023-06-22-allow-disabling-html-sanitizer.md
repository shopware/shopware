---
title: Allow disabling HTML Sanitizer
issue: NEXT-28707
---
# Core
* Added a new variable `shopware.html_sanitizer.enabled` in `shopware.yaml`.
* Changed method `\Shopware\Core\Framework\Util\HtmlSanitizer::sanitize` to skip sanitize if parameter `enabled` is false.
* Changed `\Shopware\Core\Framework\Api\Controller\InfoController::config()` to also return the `enableHtmlSanitizer` in settings config.
___
# Administration
* Added computed `enableHtmlSanitizer` in `src/app/asyncComponent/form/sw-code-editor/index.js`
* Changed method `onBlur` in `src/app/asyncComponent/form/sw-code-editor/index.js` to only call api `_admin/sanitize-html` when `enableHtmlSanitizer` is true.
