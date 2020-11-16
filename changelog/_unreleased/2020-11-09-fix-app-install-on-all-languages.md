---
title: Fix app installation if system default language is not english or german
issue: NEXT-12012
---
# Core
* Changed `toArray()` calls of `\Shopware\Core\Framework\App\Manifest\Xml\XmlElement` and derived classes, to ensure that translations exist for the system defaults language.
