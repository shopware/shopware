---
title: Fix documents template changes are not applied correctly
issue: NEXT-19784
---
# Core
* Changed method `\Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer::render` to resolve view path after dispatching `DocumentTemplateRendererParameterEvent`
* Changed method `\Shopware\Core\Framework\Framework::getTemplatePriority` to return -1
* Changed method `\Shopware\Core\System\System::getTemplatePriority` to return -1
* Changed method `\Shopware\Core\Profiling\Profiling::getTemplatePriority` to return -2
___
# Storefront
* Added new class `\Shopware\Storefront\Theme\SalesChannelThemeLoader` to load theme of a given sales channel id
* Changed class `\Shopware\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder` to implement `ResetInterface` and add the reset method to reset internal `$themes` property
___
# Administration
* Changed method `\Shopware\Administration\Administration::getTemplatePriority` to return -1
___
# Elasticsearch
* Changed method `\Shopware\Elasticsearch\Elasticsearch::getTemplatePriority` to return -1
