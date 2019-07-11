<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(SalesChannelThemeEntity $entity)
 * @method void                         set(string $key, SalesChannelThemeEntity $entity)
 * @method SalesChannelThemeEntity[]    getIterator()
 * @method SalesChannelThemeEntity[]    getElements()
 * @method SalesChannelThemeEntity|null get(string $key)
 * @method SalesChannelThemeEntity|null first()
 * @method SalesChannelThemeEntity|null last()
 */
class SalesChannelThemeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelThemeEntity::class;
    }
}
