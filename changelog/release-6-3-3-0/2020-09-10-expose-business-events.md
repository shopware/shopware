---
title:              expose business events
issue:              NEXT-10701
author:             Oliver Skroblin
author_email:       o.skroblin@shopware.com
author_github:      @OliverSkroblin
---
# Core
* Added remaining events to `\Shopware\Core\Framework\Event\BusinessEvents`
* Added `\Shopware\Core\Checkout\Cart\RuleLoader` to load all routes
* Added validation in `\Shopware\Core\Checkout\Cart\Order\OrderConverter::assembleSalesChannelContext` to make sure that all data is available 
* Added `\Shopware\Core\Framework\Event\BusinessEventCollector`, which returns a collection of all business events 
* Added `\Shopware\Core\Framework\Event\BusinessEventCollectorEvent`, which allows to mutate business events
* Added `\Shopware\Core\Framework\Event\BusinessEventCollectorResponse`, which returned by the collector
* Added `\Shopware\Core\Framework\Event\BusinessEventDefinition`, which contains all information about a business event                                        
* Deprecated `\Shopware\Core\Framework\Event\BusinessEventRegistry::getEvents` use `\Shopware\Core\Framework\Event\BusinessEventCollector::collect` instead 
* Deprecated `\Shopware\Core\Framework\Event\BusinessEventRegistry::getEventNames` use `\Shopware\Core\Framework\Event\BusinessEventCollector::collect` instead
* Deprecated `\Shopware\Core\Framework\Event\BusinessEventRegistry::getAvailableDataByEvent` use `\Shopware\Core\Framework\Event\BusinessEventCollector::collect` instead 
* Deprecated `\Shopware\Core\Framework\Event\BusinessEventRegistry::add` use `\Shopware\Core\Framework\Event\BusinessEventRegistry::addClasses` instead
* Deprecated `\Shopware\Core\Framework\Event\BusinessEventRegistry::addMultiple` use `\Shopware\Core\Framework\Event\BusinessEventRegistry::addClasses` instead
* Added `\Shopware\Core\Checkout\Order\Event\OrderStateChangeCriteriaEvent`, which allows to load additional data for order mails
___
# API
* Added `api.info.business-events` route
* Deprecated `api.info.events` use `api.info.business-events` instead
