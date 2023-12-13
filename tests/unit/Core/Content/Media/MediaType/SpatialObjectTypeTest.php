<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\MediaType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaType\SpatialObjectType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SpatialObjectType::class)]
class SpatialObjectTypeTest extends TestCase
{
    public function testName(): void
    {
        static::assertEquals('SPATIAL_OBJECT', (new SpatialObjectType())->getName());
    }
}
