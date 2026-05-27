<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\Checkout\ContinueUrlBuilder;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Pins the `continue_url` contract for both happy-path checkout handoff and
 * 4xx recovery flows. UCP overview.md §"Error Handling" REQUIRES every error
 * response that benefits from buyer interaction to carry a continue_url, so
 * a regression in the builder leaves buyers stranded.
 *
 * @internal
 */
#[CoversClass(ContinueUrlBuilder::class)]
class ContinueUrlBuilderTest extends TestCase
{
    public function testBuildsConfirmUrlByDefault(): void
    {
        $url = (new ContinueUrlBuilder())->buildForCheckout(
            $this->config(template: null),
            $this->contextWithDomain('https://shop.example'),
            'cart-token-1'
        );

        static::assertSame('https://shop.example/checkout/confirm?ucp_token=cart-token-1', $url);
    }

    public function testBuildsCartUrlForCartKind(): void
    {
        $url = (new ContinueUrlBuilder())->buildForCheckout(
            $this->config(template: null),
            $this->contextWithDomain('https://shop.example/'),
            't1',
            ContinueUrlBuilder::KIND_CART
        );

        static::assertSame('https://shop.example/checkout/cart?ucp_token=t1', $url);
    }

    public function testBuildsOrderUrlForOrderKind(): void
    {
        $url = (new ContinueUrlBuilder())->buildForCheckout(
            $this->config(template: null),
            $this->contextWithDomain('https://shop.example'),
            't1',
            ContinueUrlBuilder::KIND_ORDER
        );

        static::assertSame('https://shop.example/account/order?ucp_token=t1', $url);
    }

    public function testRendersOperatorTemplateWhenConfigured(): void
    {
        $url = (new ContinueUrlBuilder())->buildForCheckout(
            $this->config(template: '{domainUrl}/handoff?token={cartToken}&via={kind}'),
            $this->contextWithDomain('https://shop.example'),
            'tok-7'
        );

        static::assertSame('https://shop.example/handoff?token=tok-7&via=confirm', $url);
    }

    public function testRecoveryReturnsDomainRootWhenNoTemplateConfigured(): void
    {
        $url = (new ContinueUrlBuilder())->buildForRecovery(
            $this->config(template: null),
            $this->contextWithDomain('https://shop.example/')
        );

        static::assertSame('https://shop.example/', $url);
    }

    public function testRecoveryRendersTemplateWithEmptyCartToken(): void
    {
        $url = (new ContinueUrlBuilder())->buildForRecovery(
            $this->config(template: '{domainUrl}/recover/{kind}?t={cartToken}'),
            $this->contextWithDomain('https://shop.example')
        );

        static::assertSame('https://shop.example/recover/recovery?t=', $url);
    }

    public function testRecoveryReturnsNullWhenNoDomainResolvable(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn(new SalesChannelEntity());
        $context->method('getDomainId')->willReturn(null);

        $url = (new ContinueUrlBuilder())->buildForRecovery($this->config(template: null), $context);

        static::assertNull(
            $url,
            'Recovery URL must be null when no storefront domain is configured — otherwise an empty url leaks into the error envelope.'
        );
    }

    public function testFallsBackToFirstDomainWhenRequestDomainIsUnknown(): void
    {
        $context = $this->salesChannelContext(
            requestDomainId: 'unknown-domain-id',
            domains: [
                ['id' => 'd-1', 'url' => 'https://primary.example'],
                ['id' => 'd-2', 'url' => 'https://secondary.example'],
            ],
        );

        $url = (new ContinueUrlBuilder())->buildForCheckout(
            $this->config(template: null),
            $context,
            'tok'
        );

        static::assertSame('https://primary.example/checkout/confirm?ucp_token=tok', $url);
    }

    private function config(?string $template): UcpSalesChannelConfigEntity
    {
        $config = new UcpSalesChannelConfigEntity();
        $config->setContinueUrlTemplate($template);

        return $config;
    }

    private function contextWithDomain(string $domainUrl): SalesChannelContext
    {
        return $this->salesChannelContext(
            requestDomainId: 'd-1',
            domains: [['id' => 'd-1', 'url' => $domainUrl]],
        );
    }

    /**
     * @param list<array{id:string,url:string}> $domains
     */
    private function salesChannelContext(?string $requestDomainId, array $domains): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $collection = new SalesChannelDomainCollection();
        foreach ($domains as $d) {
            $domain = new SalesChannelDomainEntity();
            $domain->setId($d['id']);
            $domain->setUrl($d['url']);
            $collection->add($domain);
        }
        $salesChannel->setDomains($collection);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getDomainId')->willReturn($requestDomainId);

        return $context;
    }
}
