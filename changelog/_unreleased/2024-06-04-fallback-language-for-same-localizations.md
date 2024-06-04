---
title: Broken fallback translation with fallback of the same locale localization
issue: NEXT-36503
---
# Core
* Changed ```$fallbackLocale``` check in ```Shopware\Core\Framework\Adapter\Translation\Translator``` in method ```getCatalogue()``` to fix  fallback translation with fallback of the same locale localization
