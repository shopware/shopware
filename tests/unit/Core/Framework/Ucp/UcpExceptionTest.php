<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UcpException::class)]
class UcpExceptionTest extends TestCase
{
    public function testFeatureDisabledHas404(): void
    {
        $e = UcpException::featureDisabled();
        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(UcpException::FEATURE_DISABLED, $e->getErrorCode());
    }

    public function testVersionUnsupportedCarriesContext(): void
    {
        $e = UcpException::versionUnsupported('2024-01-01', '2026-01-23');
        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getStatusCode());
        static::assertStringContainsString('2024-01-01', $e->getMessage());
        static::assertStringContainsString('2026-01-23', $e->getMessage());
    }

    public function testCapabilityNotEnabledHas404(): void
    {
        $e = UcpException::capabilityNotEnabled('dev.ucp.shopping.cart');
        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertStringContainsString('dev.ucp.shopping.cart', $e->getMessage());
    }

    public function testProfileUnreachableHas424(): void
    {
        $e = UcpException::profileUnreachable('https://x/p', 'connection refused');
        static::assertSame(Response::HTTP_FAILED_DEPENDENCY, $e->getStatusCode());
    }

    public function testSignatureMissingHas401(): void
    {
        static::assertSame(Response::HTTP_UNAUTHORIZED, UcpException::signatureMissing()->getStatusCode());
    }

    public function testKeyCannotBeDeletedHas409(): void
    {
        $e = UcpException::keyCannotBeDeleted('kid_1', 'active', null);
        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
    }

    public function testDiscoveryBudgetHas503(): void
    {
        static::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, UcpException::discoveryBudgetExceeded()->getStatusCode());
    }
}
