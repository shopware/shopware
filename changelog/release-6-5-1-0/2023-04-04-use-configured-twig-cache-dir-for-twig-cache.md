---
title: Use configured twig.cache dir for twig cache
issue: NEXT-25869
---
# Core
* Changed the following services to use the configured `twig.cache` directory instead of the `kernel.cache_dir` directory to store the twig caches:
  * `\Shopware\Core\Framework\Adapter\Twig\TemplateFinder`
  * `\Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadSubscriber`
  * `\Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer`
  * `\Shopware\Core\Framework\Script\Execution\ScriptLoader`
___
# Upgrade Information
## Twig cache independent from kernel cache dir

You can now use the `twig.cache` configuration to configure the directory where twig caches are stored as described in the [symfony docs](https://symfony.com/doc/current/reference/configuration/twig.html#cache). This is independent from the `kernel.cache_dir` configuration, but by default it will still fallback to the `%kernel.cache_dir%/twig` directory.
This is useful when the `kernel.cache_dir` is configured to be a read-only directory.
