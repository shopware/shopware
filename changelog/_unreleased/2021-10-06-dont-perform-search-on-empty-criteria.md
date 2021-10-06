---
title: Don't perform search before read inside EntityRepository for empty criteria
issue: NEXT-12165
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\RepositorySearchDetector::isSearchRequired()` to not require search when criteria filters are empty.
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader::_read()` to perform a read if the filters and ids inside the criteria are empty.
