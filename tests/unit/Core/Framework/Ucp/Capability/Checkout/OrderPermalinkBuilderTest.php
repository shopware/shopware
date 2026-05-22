<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\Checkout\OrderPermalinkBuilder;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Regression coverage for the post-checkout `permalink_url` the UCP
 * `CheckoutController::complete()` returns. Historically the response
 * always emitted `http://localhost:8080/account/order/<id>`, which would
 * have leaked a dev-host URL into the platform agent's post-purchase
 * trust display for every real production checkout. This builder must
 * derive the URL from the resolved sales-channel domain, fall back to
 * `null` (caller omits the field) when no domain is configured, and only
 * keep the historical `localhost:8080` form when conformance mode is
 * explicitly enabled — never on regular traffic.
 *
 * @internal
 */
#[CoversClass(OrderPermalinkBuilder::class)]
class OrderPermalinkBuilderTest extends TestCase
{
    public function testUsesResolvedDomainForProductionTraffic(): void
    {
        $builder = new OrderPermalinkBuilder();
        $context = $this->salesChannelContextWithDomains(
            requestDomainId: 'd-2',
            domains: [
                ['id' => 'd-1', 'url' => 'https://shop.example.com'],
                ['id' => 'd-2', 'url' => 'https://de.shop.example.com'],
            ],
        );

        $url = $builder->build($context, 'order-42', conformanceMode: false);

        static::assertSame('https://de.shop.example.com/account/order/order-42', $url);
    }

    public function testFallsBackToFirstDomainWhenRequestDomainIdIsNull(): void
    {
        $builder = new OrderPermalinkBuilder();
        $context = $this->salesChannelContextWithDomains(
            requestDomainId: null,
            domains: [
                ['id' => 'd-1', 'url' => 'https://shop.example.com'],
                ['id' => 'd-2', 'url' => 'https://de.shop.example.com'],
            ],
        );

        $url = $builder->build($context, 'order-42', conformanceMode: false);

        static::assertSame('https://shop.example.com/account/order/order-42', $url);
    }

    public function testStripsTrailingSlashFromDomainUrl(): void
    {
        $builder = new OrderPermalinkBuilder();
        $context = $this->salesChannelContextWithDomains(
            requestDomainId: 'd-1',
            domains: [['id' => 'd-1', 'url' => 'https://shop.example.com/']],
        );

        $url = $builder->build($context, 'order-42', conformanceMode: false);

        static::assertSame('https://shop.example.com/account/order/order-42', $url);
    }

    public function testReturnsNullWhenNoDomainsConfiguredAndConformanceModeOff(): void
    {
        $builder = new OrderPermalinkBuilder();
        $context = $this->salesChannelContextWithDomains(requestDomainId: null, domains: []);

        $url = $builder->build($context, 'order-42', conformanceMode: false);

        static::assertNull(
            $url,
            'A production checkout response must omit permalink_url rather than emit a misleading dev URL.'
        );
    }

    public function testReturnsNullWhenDomainsCollectionIsNullAndConformanceModeOff(): void
    {
        $builder = new OrderPermalinkBuilder();
        $context = $this->salesChannelContextWithDomains(requestDomainId: null, domains: null);

        static::assertNull($builder->build($context, 'order-42', conformanceMode: false));
    }

    public function testKeepsHistoricalLocalhostFallbackOnlyForConformanceMode(): void
    {
        $builder = new OrderPermalinkBuilder();
        $context = $this->salesChannelContextWithDomains(requestDomainId: null, domains: []);

        $url = $builder->build($context, 'order-42', conformanceMode: true);

        static::assertSame('http://localhost:8080/account/order/order-42', $url);
    }

    public function testProductionResponseNeverContainsLocalhostEvenWhenDomainUrlIsEmpty(): void
    {
        $builder = new OrderPermalinkBuilder();
        $context = $this->salesChannelContextWithDomains(
            requestDomainId: 'd-1',
            domains: [['id' => 'd-1', 'url' => '']],
        );

        $url = $builder->build($context, 'order-42', conformanceMode: false);

        // Empty domain url is treated the same as "no domains configured":
        // production never emits localhost.
        static::assertNull($url);
        static::assertNotEquals('http://localhost:8080/account/order/order-42', $url);
    }

    /**
     * @param list<array{id: string, url: string}>|null $domains
     */
    private function salesChannelContextWithDomains(?string $requestDomainId, ?array $domains): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        if ($domains !== null) {
            $collection = new SalesChannelDomainCollection();
            foreach ($domains as $d) {
                $domain = new SalesChannelDomainEntity();
                // setId() also sets _uniqueIdentifier, which is what
                // EntityCollection::get($id) looks up by.
                $domain->setId($d['id']);
                $domain->setUrl($d['url']);
                $collection->add($domain);
            }
            $salesChannel->setDomains($collection);
        }

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getDomainId')->willReturn($requestDomainId);

        return $context;
    }
}
