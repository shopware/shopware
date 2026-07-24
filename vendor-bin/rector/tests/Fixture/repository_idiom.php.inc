<?php

namespace Shopware\Tests\Rector\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

function repositoryIdiom(EntitySearchResult $result): array
{
    if ($result->has('some-id')) {
        return $result->getIds();
    }

    return array_keys($result->getElements());
}

?>
-----
<?php

namespace Shopware\Tests\Rector\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

function repositoryIdiom(EntitySearchResult $result): array
{
    if ($result->getEntities()->has('some-id')) {
        return $result->getEntities()->getIds();
    }

    return array_keys($result->getEntities()->getElements());
}

?>
