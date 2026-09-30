<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\MediaType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaType\SpatialSceneType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SpatialSceneType::class)]
class SpatialSceneTypeTest extends TestCase
{
    public function testName(): void
    {
        static::assertSame('SPATIAL_SCENE', (new SpatialSceneType())->getName());
    }
}
