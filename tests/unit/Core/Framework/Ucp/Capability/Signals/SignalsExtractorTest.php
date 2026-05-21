<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Signals;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\CapabilityIntersection;
use Shopware\Core\Framework\Ucp\Capability\Signals\SignalsExtractor;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(SignalsExtractor::class)]
class SignalsExtractorTest extends TestCase
{
    public function testDropsSignalsWhenRequestSignatureWasNotVerified(): void
    {
        $extractor = new SignalsExtractor();

        $signals = $extractor->extract(
            ['signals' => ['dev.ucp.buyer_ip' => '203.0.113.42']],
            $this->context(),
            signatureVerified: false
        );

        static::assertSame([], $signals);
    }

    public function testAcceptsSpecObjectMapWhenSignatureWasVerified(): void
    {
        $extractor = new SignalsExtractor();

        $signals = $extractor->extract(
            [
                'signals' => [
                    'dev.ucp.buyer_ip' => '203.0.113.42',
                    'evil.example.signal' => 'ignored',
                ],
            ],
            $this->context(),
            signatureVerified: true
        );

        static::assertSame(['dev.ucp.buyer_ip' => '203.0.113.42'], $signals);
    }

    private function context(): UcpRequestContext
    {
        $config = new UcpSalesChannelConfigEntity();
        $config->setSalesChannelId('00000000000000000000000000000000');
        $config->setPlatformAllowlist(null);

        return new UcpRequestContext(
            config: $config,
            salesChannelContext: $this->createMock(SalesChannelContext::class),
            intersection: new CapabilityIntersection(capabilities: [], protocolVersion: '2026-01-23'),
            platformProfileUri: 'https://platform.example/profile.json'
        );
    }
}
