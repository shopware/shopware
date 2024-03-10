---
title: Prevent predictable empty request in RepositoryIterator and improve developer experience
issue: NEXT-11081
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator::reset` to either remove `increment` filter if the iterated entity has an auto increment column or otherwise removes the offset. This helps to implement iterator resets without knowing how the iteration is implemented
* Added internal state into `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator`, whether the previous `fetch` or `fetchIds` result had less items than expected and therefore the next search will be empty anyways 
* Added methods `iterateIds` and `iterateEntities` to class `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator` to automatically perform iteration on respectively `fetchIds` and `fetch`
