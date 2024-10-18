---
title: Add Twig functions and tokens with their alternatives with inheritance
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Add service `\Shopware\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension` to add new Twig functions `sw_block` with expression `\Shopware\Core\Framework\Adapter\Twig\Node\SwBlockReferenceExpression`, new Twig function `sw_source`, new Twig function `sw_include`, new Twig token `sw_use` with `\Shopware\Core\Framework\Adapter\Twig\TokenParser\UseTokenParser`, new Twig token `sw_embed` with `\Shopware\Core\Framework\Adapter\Twig\TokenParser\EmbedTokenParser`, new Twig token `sw_from` with `\Shopware\Core\Framework\Adapter\Twig\TokenParser\FromTokenParser` and new Twig token `sw_import` with `\Shopware\Core\Framework\Adapter\Twig\TokenParser\ImportTokenParser`
* Change constructor parameter in `\Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension` from `\Shopware\Core\Framework\Adapter\Twig\TemplateFinder` to `\Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface`
* Change return type of method `\Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface::getFinder` from `\Shopware\Core\Framework\Adapter\Twig\TemplateFinder` to `\Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface`
___
# Storefront
* Add Twig function `sw_block`, that must be used, when the template reference is used and inheritance is expected
* Add Twig function `sw_source`, that can be used, to include the source of a file with inheritance, so its content can be exchanged
* Add Twig function `sw_include`, that can be used, to include a file like using `sw_include` but as a function
* Add Twig token `sw_use`, that can be used, to include blocks out of other files with inheritance, so its content can be exchanged
* Add Twig token `sw_embed`, that can be used, to include other templates with blocks as slots with inheritance, so its content can be exchanged
* Add Twig token `sw_from`, that can be used, to include single macros out of other files with inheritance, so its content can be exchanged
* Add Twig token `sw_import`, that can be used, to include macros out of other files with inheritance, so its content can be exchanged
