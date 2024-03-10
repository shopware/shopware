---
title: Prevent predictable empty request in RepositoryIterator and improve developer experience
issue: NEXT-11081
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added methods `iterateIds` and `iterateEntities` to class `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator` to automatically perform iteration on respectively `fetchIds` and `fetch` with predicted stop when less entries were fetched than limited to
