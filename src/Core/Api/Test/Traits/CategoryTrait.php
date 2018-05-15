<?php

namespace Shopware\Api\Test\Traits;

use Shopware\Content\Category\Repository\CategoryRepository;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Uuid;

trait CategoryTrait
{
    public function createCategory(ApplicationContext $context, array $override = []): string
    {
        $id = Uuid::uuid4();
        $payload = array_merge(
            [
                'id' => $id->getHex(),
                'name' => 'Random category name',
                'catalogId' => $context->getCatalogIds()[0]
            ],
            $override
        );

        self::$kernel->getContainer()->get(CategoryRepository::class)->create([$payload], $context);

        return $payload['id'];
    }
}