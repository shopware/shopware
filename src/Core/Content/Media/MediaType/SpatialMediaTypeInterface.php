<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

/**
 * Marks a media type that is shown by the spatial viewer rather than as a picture.
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
#[Package('discovery')]
interface SpatialMediaTypeInterface
{
}
