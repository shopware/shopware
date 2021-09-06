---
title: Optimize export performance
issue: NEXT-10315
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `Core\Framework\Adapter\Twig\TwigVariableParser`, which can be used to detect variable access inside a twig template
* Added `Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::getAssociationPath()`, which detects the path to an association of the provided accessor
* Added `Core\Framework\DataAbstractionLayer\Event\EntityLoadedContainerEvent` which is used as container event for all nested entity loaded events.
* Deprecated `Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent::$nested` and all related functions, the functionality is no more used and will be removed with 6.5.
* Added `src/Core/Framework/DataAbstractionLayer/Event/EntityLoadedEventFactory` which generates all entity loaded events for the provided entities and nested entities.
* Added `src/Core/Framework/DataAbstractionLayer/Entity::getInternalEntityName()`, which allows access to configured entity name of the entity definition behind the struct.
* Added new __construct dependency `?EntityLoadedEventFactory $eventFactory` in `EntityRepository.php`, If the dependency is not resolved via the __construct, a compiler pass takes care of it. The compiler is removed with 6.5.
___
# Upgrade Information
## New __construct dependency

A new dependency has been added for the `EntityRepository` and the `SalesChannelEntityRepository`.
If you have defined the repository class yourself in your services.xml, you have to adapt it until 6.5 as follows:

```before
<service class="Shopware\Core\Framework\DataAbstractionLayer\EntityRepository" id="product.repository">
    <argument type="service" id="Shopware\Core\Content\Product\ProductDefinition"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\VersionManager"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface"/>
    <argument type="service" id="Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator.inner"/>
    <argument type="service" id="event_dispatcher"/>
</service>
```

Now you have to inject the `Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory` service after the `event_dispatcher`
```after
<service class="Shopware\Core\Framework\DataAbstractionLayer\EntityRepository" id="product.repository">
    <argument type="service" id="Shopware\Core\Content\Product\ProductDefinition"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\VersionManager"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface"/>
    <argument type="service" id="Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator.inner"/>
    <argument type="service" id="event_dispatcher"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory"/>
</service>
```
Up to 6.5, a compiler pass ensures that the event factory is injected via the `setEntityLoadedEventFactory` method.
