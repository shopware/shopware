---
title: Add Twig functions and tokens with their alternatives with inheritance
issue: NEXT-38820
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added service `\Shopware\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension` to add the following Twig functions/tokens with inheritance:
  * new Twig function `sw_block` with expression `\Shopware\Core\Framework\Adapter\Twig\Node\SwBlockReferenceExpression`
  * new Twig function `sw_source` but with inheritance
  * new Twig function `sw_include` but with inheritance as alternative to `sw_include` token
  * new Twig token `sw_use` with `\Shopware\Core\Framework\Adapter\Twig\TokenParser\UseTokenParser`
  * new Twig token `sw_embed` with `\Shopware\Core\Framework\Adapter\Twig\TokenParser\EmbedTokenParser`
  * new Twig token `sw_from` with `\Shopware\Core\Framework\Adapter\Twig\TokenParser\FromTokenParser`
  * new Twig token `sw_import` with `\Shopware\Core\Framework\Adapter\Twig\TokenParser\ImportTokenParser`
___
# Storefront
* Added Twig function `sw_block`, that must be used, when the template reference is used and inheritance is expected
* Added Twig function `sw_source`, that can be used, to include the source of a file with inheritance, so its content can be exchanged
* Added Twig function `sw_include`, that can be used, to include a file like using `include` (limited to one file at once with inheritance)
* Added Twig token `sw_use`, that can be used, to include blocks from other files with inheritance, so its content can be exchanged
* Added Twig token `sw_embed`, that can be used, to include other templates with blocks as slots with inheritance, so its content can be exchanged
* Added Twig token `sw_from`, that can be used, to include single macros from other files with inheritance, so its content can be exchanged
* Added Twig token `sw_import`, that can be used, to include macros from other files with inheritance, so its content can be exchanged
