---
title: Add support for extended definition of attribute entity
issue: #8393
author: Thuy Le
author_email: thuy.le@shopware.com
author_github: @thuylt
---
# Core
* Added `shopware.entity.definition` tag to `AttributeEntityDefinition`, `AttributeTranslationDefinition` and `AttributeMappingDefinition` in `Shopware\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass`.
* Changed `Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass` to add metadata when creating an instance of `AttributeEntityDefinition` class.
* Changed `Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass` to skip adding a repository definition to compiled container for `AttributeEntityDefinition` class.
* Changed the compiler pass priority for `AttributeEntityCompilerPass` from `beforeRemoving` to `beforeOptimization` in `Shopware\Core\Framework\Framework`.
