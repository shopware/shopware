<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

/**
 * Marks a media type whose media may exist without a file.
 *
 * For such a media a missing file is a valid state and not an upload that never finished, so the
 * housekeeping that removes incomplete uploads has to leave it alone. Extensions implement it for
 * media that stands for something of their own rather than for a file.
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
#[Package('discovery')]
interface FilelessMediaTypeInterface
{
}
