<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

/**
 * Marks a media type that is shown by the spatial viewer rather than as a picture.
 *
 * What the viewer is handed differs per type - a file for one, state fetched elsewhere for another -
 * so the interface says only that the spatial viewer is the right renderer. Extensions implement it
 * to have their own media types picked up by the surfaces that already render spatial media.
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
#[Package('discovery')]
interface SpatialMediaTypeInterface
{
}
