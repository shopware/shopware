<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
#[Package('discovery')]
class SpatialObjectType extends MediaType
{
    protected string $name = 'SPATIAL_OBJECT';
}
