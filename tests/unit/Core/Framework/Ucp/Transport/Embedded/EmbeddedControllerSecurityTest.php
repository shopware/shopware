<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Transport\Embedded;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\CapabilityIntersection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Embedded\EmbeddedController;
use Shopware\Core\Framework\Ucp\Transport\Embedded\EmbeddedSessionFactory;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EmbeddedController::class)]
class EmbeddedControllerSecurityTest extends TestCase
{
    public function testCheckoutRejectsMissingOriginInsteadOfFallingBackToWildcard(): void
    {
        $request = Request::create('/ucp/embedded/checkout/cart-1', 'GET');
        $request->attributes->set(UcpRequestContext::REQUEST_ATTRIBUTE, $this->ucpContext());

        $controller = new EmbeddedController($this->createMock(EmbeddedSessionFactory::class));

        $this->expectException(UcpException::class);
        $controller->checkout($request, 'cart-1');
    }

    public function testCheckoutRejectsNonHttpsNonLocalOrigin(): void
    {
        $request = Request::create('/ucp/embedded/checkout/cart-1?origin=http://evil.example', 'GET');
        $request->attributes->set(UcpRequestContext::REQUEST_ATTRIBUTE, $this->ucpContext());

        $controller = new EmbeddedController($this->createMock(EmbeddedSessionFactory::class));

        $this->expectException(UcpException::class);
        $controller->checkout($request, 'cart-1');
    }

    private function ucpContext(): UcpRequestContext
    {
        $config = new UcpSalesChannelConfigEntity();
        $config->setSalesChannelId('00000000000000000000000000000000');

        return new UcpRequestContext(
            config: $config,
            salesChannelContext: $this->createMock(SalesChannelContext::class),
            intersection: new CapabilityIntersection(capabilities: [], protocolVersion: '2026-01-23'),
            platformProfileUri: 'https://platform.example/profile.json'
        );
    }
}
