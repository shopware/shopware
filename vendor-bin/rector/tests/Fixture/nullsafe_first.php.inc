<?php

namespace Shopware\Tests\Rector\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

function nullsafeFirst(?EntitySearchResult $result): ?object
{
    return $result?->first();
}

?>
-----
<?php

namespace Shopware\Tests\Rector\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

function nullsafeFirst(?EntitySearchResult $result): ?object
{
    return $result?->getEntities()->first();
}

?>
