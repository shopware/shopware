---
title: Allow autowiring of `EntityRepository` type
issue: NEXT-22613
---
# Core
* Changed `\Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass` to create autowiring aliases for the registered entity repositories also for `EntityRepository` type and not just for the deprecated `EntityRepositoryInterface`.
