---
title: StringTemplateRenderer throws AdapterException with error code "FRAMEWORK__INVALID_TEMPLATE_SYNTAX" due invalid template syntax
issue: NEXT-30983
author: Krzykawski
author_email: m.krzykawski@shopware.com
author_github: Krzykawski
---
# Core
* Added the `Shopware\Core\Framework\Adapter\AdapterException::invalidTemplateSyntax` function with error code `FRAMEWORK__INVALID_TEMPLATE_SYNTAX`
  * This error code uses the error log level `notice` in `Core/Framework/Resources/config/packages/shopware.yaml` that the exception will not be written to the system log by default
* Changed the `Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer::render` function which throws now the `AdapterException` with error code `FRAMEWORK__INVALID_TEMPLATE_SYNTAX` due invalid user input in twig templates instead of `Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException`
  * `StringTemplateRenderingException` will still be thrown due `Twig\Error\LoaderError` and `Twig\Error\RuntimeError` exceptions
