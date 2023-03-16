---
title: Final storable flow
issue: NEXT-25696
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
author_github: @OliverSkroblin
---
# Core
* Removed `StorableFlow` @internal flag and define the class as final
* Removed `FlowAction` @internal flag, to allow external developers provide own flow actions
* Removed `DemodataGeneratorInterface` @internal flag, to provide own demo data generators via extensions
* Changed `ContainerFacade` @internal flag to @final, due to the fact that apps already consum this service as public API
* Removed `AbstractRuleLoader` @internal flag, to allow decorating
* Removed `AbstractFlowLoader` @internal flag, to allow developers loading of flows by their own
* Removed @internal flag of `EntityIndexerRegistry`, to allow developers to use the service (including BC promise)
* Removed @internal flag from `EntityIndexingMessage` DTO, to allow developers to create entity indexing message by their own
* Changed @internal flag for demo data domain classes, which are required to add own demo data as developer. 
* Changed @internal flag for `EntityScoreQueryBuilder`, to @final to allow developers to use the service (including BC promise)
* Changed @internal flag for `IncrementGatewayRegistry`, to @final to allow developers to use the service (including BC promise)
* Added `\Shopware\Core\Framework\Util\Json` public api class for unified json encoding
* Removed @internal flag for `AppEntity` 
* Removed @internal flag for `DocumentConfigLoader`, to allow developers to use the service (including BC promise)
* Removed @internal flag for `OrderLineItemEntity::$promotion`, to allow developers to access the property
