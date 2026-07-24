<?php

namespace Shopware\Tests\Rector\Fixture;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;

function subclassResult(ProductListingResult $result): ?object
{
    return $result->first();
}

?>
-----
<?php

namespace Shopware\Tests\Rector\Fixture;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;

function subclassResult(ProductListingResult $result): ?object
{
    return $result->getEntities()->first();
}

?>
