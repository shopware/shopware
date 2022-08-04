---
title: Allow autowiring of `EntityRepository` and `SalesChannelRepository` type
issue: NEXT-22613
---
# Core
* Changed `\Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass` to create autowiring aliases for the registered entity repositories also for `EntityRepository` type and not just for the deprecated `EntityRepositoryInterface`.
* Changed `\Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass` to create autowiring aliases for the registered entity sale channel repositories `SalesChannelRepository`.
