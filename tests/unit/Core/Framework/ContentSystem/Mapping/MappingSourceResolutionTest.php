<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingSourceResolution::class)]
class MappingSourceResolutionTest extends TestCase
{
    public function testAnExplicitNullIsAResolvedValue(): void
    {
        $result = MappingSourceResolution::resolved(null);

        static::assertTrue($result->hasValue);
        static::assertNull($result->value);
        static::assertFalse($result->isNoValue());
    }

    public function testMissingDataHasNoResolvedValue(): void
    {
        $result = MappingSourceResolution::noValue();

        static::assertFalse($result->hasValue);
        static::assertNull($result->value);
        static::assertTrue($result->isNoValue());
    }
}
