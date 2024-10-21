<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(EntityCacheKeyGenerator::class)]
class EntityCacheKeyGeneratorTest extends TestCase
{
    public function testBuildCmsTag(): void
    {
        static::assertSame('cms-page-foo', EntityCacheKeyGenerator::buildCmsTag('foo'));
    }

    public function testBuildProductTag(): void
    {
        static::assertSame('product-foo', EntityCacheKeyGenerator::buildProductTag('foo'));
    }

    public function testBuildStreamTag(): void
    {
        static::assertSame('product-stream-foo', EntityCacheKeyGenerator::buildStreamTag('foo'));
    }

    #[DataProvider('criteriaHashProvider')]
    public function testCriteriaHash(Criteria $criteria, string $hash): void
    {
        $generator = new EntityCacheKeyGenerator();

        static::assertSame($hash, $generator->getCriteriaHash($criteria));
    }

    public static function criteriaHashProvider(): \Generator
    {
        yield 'empty' => [
            new Criteria(),
            '6f1868158423d60724dd3071c2d6f525',
        ];

        yield 'prefix-filter' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar')),
            '6da5430a8e30985dcf70e1cce8059641',
        ];

        // this has a different hash because of a different filter type used
        yield 'suffix-filter' => [
            (new Criteria())->addFilter(new SuffixFilter('foo', 'bar')),
            'bc3f8967e44fa629e6a24e76b9060085',
        ];

        yield 'filter+sort' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo')),
            'd877f8f5b53b110aafb704ac12a5e579',
        ];

        yield 'filter+sort+sort-desc' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo', FieldSorting::DESCENDING)),
            '937c265ec89cb32660de09457f16c5fd',
        ];

        yield 'filter+agg' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addAggregation(new TermsAggregation('foo', 'foo')),
            'c29788d8da490513f252b92f49a91773',
        ];
    }

    #[DataProvider('contextHashProvider')]
    public function testContextHash(SalesChannelContext $compared): void
    {
        $generator = new EntityCacheKeyGenerator();

        static::assertNotEquals(
            $generator->getSalesChannelContextHash(new DummyContext(), ['test']),
            $generator->getSalesChannelContextHash($compared, ['test'])
        );
    }

    public static function contextHashProvider(): \Generator
    {
        yield 'tax state considered for hash' => [
            (new DummyContext())->setTaxStateFluent(CartPrice::TAX_STATE_NET),
        ];

        yield 'currency id considered for hash' => [
            (new DummyContext())->setCurrencyId('foo'),
        ];

        yield 'sales channel id considered for hash' => [
            (new DummyContext())->setSalesChannelId('foo'),
        ];

        yield 'language id chain considered for hash' => [
            (new DummyContext())->setLanguageChain(['foo']),
        ];

        yield 'version considered for hash' => [
            (new DummyContext())->setVersionId('foo'),
        ];

        yield 'rounding mode considered for hash' => [
            (new DummyContext())->setItemRoundingFluent(new CashRoundingConfig(2, 0.5, true)),
        ];

        yield 'rules considered for hash' => [
            (new DummyContext())->setAreaRuleIdsFluent(['test' => ['foo']]),
        ];
    }
}

/**
 * @internal
 */
class DummyContext extends SalesChannelContext
{
    public function __construct()
    {
        $source = new SalesChannelApiSource(TestDefaults::SALES_CHANNEL);

        parent::__construct(
            new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, true, CartPrice::TAX_STATE_GROSS),
            'token',
            'domain-id',
            (new SalesChannelEntity())->assign(['id' => TestDefaults::SALES_CHANNEL]),
            (new CurrencyEntity())->assign(['id' => Defaults::CURRENCY]),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CustomerEntity(),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            []
        );
    }

    public function setSalesChannelId(string $salesChannelId): self
    {
        $this->salesChannel = (new SalesChannelEntity())->assign(['id' => $salesChannelId]);

        return $this;
    }

    public function setCurrencyId(string $currencyId): self
    {
        $this->currency = (new CurrencyEntity())->assign(['id' => $currencyId]);

        return $this;
    }

    /**
     * @param list<string> $chain
     */
    public function setLanguageChain(array $chain): self
    {
        $this->context->assign(['languageIdChain' => $chain]);

        return $this;
    }

    public function setVersionId(string $versionId): self
    {
        $this->context->assign(['versionId' => $versionId]);

        return $this;
    }

    public function setTaxStateFluent(string $taxState): self
    {
        $this->context->setTaxState($taxState);

        return $this;
    }

    /**
     * @param array<string, string[]> $rules
     */
    public function setAreaRuleIdsFluent(array $rules): self
    {
        $this->setAreaRuleIds($rules);

        return $this;
    }

    public function setItemRoundingFluent(CashRoundingConfig $rounding): self
    {
        $this->itemRounding = $rounding;

        return $this;
    }
}
