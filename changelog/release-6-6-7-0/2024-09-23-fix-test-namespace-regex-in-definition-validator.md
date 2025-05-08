---
title: Fix test namespace regex in DefinitionValidator
issue: NEXT-37900
---
# Core
* Changed regex used in `\Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator` to only skip test namespaces and not any namespace that ends with an `s`.