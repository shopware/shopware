<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelTwigFilter;

/**
 * @internal
 */
#[CoversClass(GaranLabelTwigFilter::class)]
class GaranLabelTwigFilterTest extends TestCase
{
    public function testGetFiltersRegistersSwGaranLabelDuration(): void
    {
        $filter = new GaranLabelTwigFilter(new GaranLabelDurationFormatter());

        $filters = $filter->getFilters();

        static::assertCount(1, $filters);
        static::assertSame('sw_garan_label_duration', $filters[0]->getName());
    }

    public function testFormatDurationDelegatesToFormatter(): void
    {
        $filter = new GaranLabelTwigFilter(new GaranLabelDurationFormatter());

        static::assertSame('3', $filter->formatDuration(36));
        static::assertNull($filter->formatDuration(12));
        static::assertNull($filter->formatDuration(null));
    }
}
