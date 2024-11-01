---
title: Establish mapping entity definition
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added classes to support mapping entities `\Shopware\Core\Framework\DataAbstractionLayer\MappingEntity` and `\Shopware\Core\Framework\DataAbstractionLayer\MappingEntityCollection`
* Deprecated exception `\Shopware\Core\Framework\DataAbstractionLayer\Exception\MappingEntityClassesException` as it will not be thrown anymore by default
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition::getCollectionClass` and `\Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition::getEntityClass` to not throw an exception but the new classes instead
___
# API
* Added support to search mapping entities like `product_category`, `product_tag`, `customer_tag` and more
___
# Upgrade Information
## Static type analysis

Mapping entities were not previously supported to be queried as the entity definition prevent deserialization.
In usage one had to use the EntityRepository to write mappings (create or delete) which also allow reading but were stopped at deserialization.
EntityRepository has support for generic static type analysis using Psalm and PHPStan by providing typed comments.
For this one had to reference a collection to provide better code analysis but due to missing deserialization there was no correct type hint to provide although the search way was not usable.
Now you can add type hints reference `\Shopware\Core\Framework\DataAbstractionLayer\MappingEntityCollection` for clear code completion and code analysis.
