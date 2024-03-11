<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
#[Package('buyers-experience')]
class SpatialObjectType extends MediaType
{
    protected $name = 'SPATIAL_OBJECT';
}
