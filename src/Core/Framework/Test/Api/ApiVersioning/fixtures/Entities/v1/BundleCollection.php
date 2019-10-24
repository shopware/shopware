<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(BundleEntity $entity)
 * @method void              set(string $key, BundleEntity $entity)
 * @method BundleEntity[]    getIterator()
 * @method BundleEntity[]    getElements()
 * @method BundleEntity|null get(string $key)
 * @method BundleEntity|null first()
 * @method BundleEntity|null last()
 */
class BundleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BundleEntity::class;
    }
}
