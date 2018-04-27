<?php

namespace Shopware\Api\Test\Traits;

use Shopware\Api\Catalog\Repository\CatalogRepository;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Uuid;

trait CatalogTrait
{
    public function createCatalog(ApplicationContext $context, array $override = []): string
    {
        $catalogId = Uuid::uuid4();
        $catalog = array_merge(
            [
                'id' => $catalogId->getHex(),
                'name' => 'unit test catalog'
            ],
            $override
        );

        self::$kernel->getContainer()->get(CatalogRepository::class)->create([$catalog], $context);

        return $catalog['id'];
    }
}