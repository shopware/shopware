<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LegalGuaranteeNotice\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(LegalGuaranteeNoticeRouteResponse::class)]
class LegalGuaranteeNoticeRouteResponseTest extends TestCase
{
    public function testResponseWrapsSvgAndLink(): void
    {
        $response = new LegalGuaranteeNoticeRouteResponse('<svg>notice</svg>', 'https://europa.eu/youreurope/garantien');

        static::assertSame('<svg>notice</svg>', $response->getObject()->get('svg'));
        static::assertSame('https://europa.eu/youreurope/garantien', $response->getObject()->get('link'));
        static::assertSame('legal_guarantee_notice', $response->getObject()->getApiAlias());
    }

    public function testResponseAllowsNullValues(): void
    {
        $response = new LegalGuaranteeNoticeRouteResponse(null, null);

        static::assertNull($response->getObject()->get('svg'));
        static::assertNull($response->getObject()->get('link'));
    }
}
