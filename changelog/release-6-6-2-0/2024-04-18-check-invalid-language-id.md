---
title: Check for invalid language id on language change
issue: NEXT-35339
---
# Storefront
* Changed `\Shopware\Storefront\Controller\ContextController::switchLanguage` to check on valid Uuid for `languageId`
