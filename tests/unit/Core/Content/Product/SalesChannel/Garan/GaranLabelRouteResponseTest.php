<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Garan\GaranLabelRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelRouteResponse::class)]
class GaranLabelRouteResponseTest extends TestCase
{
    public function testResponseWrapsSvg(): void
    {
        $response = new GaranLabelRouteResponse('<svg>rendered</svg>');

        static::assertSame('<svg>rendered</svg>', $response->getObject()->get('svg'));
        static::assertSame('garan_label', $response->getObject()->getApiAlias());
    }

    public function testResponseAllowsNullSvg(): void
    {
        $response = new GaranLabelRouteResponse(null);

        static::assertNull($response->getObject()->get('svg'));
    }
}
