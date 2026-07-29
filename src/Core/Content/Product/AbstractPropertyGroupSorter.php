<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractPropertyGroupSorter
{
    abstract public function getDecorated(): AbstractPropertyGroupSorter;

    /**
     * @deprecated tag:v6.8.0 - Will be removed in v6.8.0. Use sortUsingLocaleCode() instead.
     *
     * @param EntityCollection<PropertyGroupOptionEntity|PartialEntity> $options
     */
    abstract public function sort(EntityCollection $options): PropertyGroupCollection;

    /**
     * @deprecated tag:v6.8.0 - Will become abstract in v6.8.0.
     *
     * @param EntityCollection<PropertyGroupOptionEntity|PartialEntity> $options
     */
    public function sortUsingLocaleCode(EntityCollection $options, string $localeCode): PropertyGroupCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "sortUsingLocaleCode()" will become abstract in v6.8.0.0. Override it in %s, as the "sort()" method will be removed.',
                static::class
            )
        );

        return $this->sort($options);
    }
}
