---
title: repair load custom seo url twig extensions
issue: NEXT-25509
author: Björn Herzke
author_email: bjoern.herzke@brandung.de
author_github: wrongspot
---
# Core 
* Added `Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory`
* Changed `Shopware\Core\Content\Seo\SeoUrlGenerator::__construct` removed `TwigVariableParser` and use `TwigVariableParserFactory` instead
* Changed `Shopware\Core\Content\ProductExport\Service\ProductExportGenerator::__construct` removed `TwigVariableParser` and use `TwigVariableParserFactory` instead
* Deprecated direct usage of `Shopware\Core\Framework\Adapter\Twig\TwigVariableParser`-service use `Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory` instead
