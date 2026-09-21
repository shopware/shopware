<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

/**
 * Media of this type intentionally never carries a file. The media row is the identity of a
 * spatial scene; the scene data itself lives in a separate entity referencing this media.
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
#[Package('discovery')]
class SpatialSceneType extends MediaType
{
    protected string $name = 'SPATIAL_SCENE';
}
