---
title: Allow autoconfigure attribute on attribute entities
issue: #11915
---
# Core
* Changed CompilerPass priority of `\Shopware\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass` to 99, to only run after symfony's `\Symfony\Component\DependencyInjection\Compiler\RegisterAutoconfigureAttributesPass` tag, that way services with the `AutoconfigureTag` attribute are correctly picked up by our compiler pass.
