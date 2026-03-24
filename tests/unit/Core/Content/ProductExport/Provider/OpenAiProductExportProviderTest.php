<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\OpenAiProductExportProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(OpenAiProductExportProvider::class)]
class OpenAiProductExportProviderTest extends TestCase
{
    public function testGetTechnicalNameReturnsOpenAi(): void
    {
        $provider = new OpenAiProductExportProvider($this->createSalesChannelRepository());

        static::assertSame('open-ai', $provider->getTechnicalName());
    }

    public function testExtendRenderContextUsesCountriesFromSalesChannelContext(): void
    {
        $repository = $this->createSalesChannelRepository();
        $provider = new OpenAiProductExportProvider($repository);
        $salesChannel = $this->createSalesChannel(['DE', null, 'FR']);

        $renderContext = $provider->extendRenderContext(
            $this->createProductExport(),
            $this->createSalesChannelContext($salesChannel),
            ['existing' => 'value']
        );

        static::assertSame('value', $renderContext['existing']);
        static::assertInstanceOf(ArrayStruct::class, $renderContext['provider']);

        $providerContext = $renderContext['provider'];

        static::assertSame('open-ai', $providerContext->get('name'));
        static::assertSame('DE', $providerContext->get('storeCountry'));
        static::assertSame(['DE', 'FR'], $providerContext->get('targetCountries'));
        static::assertSame('Merchant', $providerContext->get('sellerName'));
        static::assertSame('https://merchant.example', $providerContext->get('sellerUrl'));
        static::assertSame('https://merchant.example', $providerContext->get('returnPolicyUrl'));
        static::assertTrue($providerContext->get('isEligibleSearch'));
        static::assertFalse($providerContext->get('isEligibleCheckout'));
    }

    public function testExtendRenderContextLoadsCountriesFromRepositoryWhenAssociationIsNotLoaded(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->createSalesChannel();
        $salesChannelId = $salesChannel->getId();
        $fallbackSalesChannel = $this->createSalesChannel([null, 'US']);

        $repository = $this->createSalesChannelRepository([
            /**
             * @return list<SalesChannelEntity>
             */
            static function (Criteria $criteria, Context $repositoryContext) use ($context, $salesChannelId, $fallbackSalesChannel): array {
                static::assertSame([$salesChannelId], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('countries'));
                static::assertSame($context, $repositoryContext);

                return [$fallbackSalesChannel];
            },
        ]);

        $provider = new OpenAiProductExportProvider($repository);

        $renderContext = $provider->extendRenderContext(
            $this->createProductExport(),
            $this->createSalesChannelContext($salesChannel, $context),
            []
        );

        static::assertInstanceOf(ArrayStruct::class, $renderContext['provider']);
        static::assertSame(['US'], $renderContext['provider']->get('targetCountries'));
    }

    public function testExtendRenderContextSetsTargetCountriesToNullWhenTheyCannotBeResolved(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->createSalesChannel();
        $salesChannelId = $salesChannel->getId();
        $fallbackSalesChannel = $this->createSalesChannel();

        $repository = $this->createSalesChannelRepository([
            /**
             * @return list<SalesChannelEntity>
             */
            static function (Criteria $criteria, Context $repositoryContext) use ($context, $salesChannelId, $fallbackSalesChannel): array {
                static::assertSame([$salesChannelId], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('countries'));
                static::assertSame($context, $repositoryContext);

                return [$fallbackSalesChannel];
            },
        ]);

        $provider = new OpenAiProductExportProvider($repository);

        $renderContext = $provider->extendRenderContext(
            $this->createProductExport(),
            $this->createSalesChannelContext($salesChannel, $context),
            []
        );

        static::assertInstanceOf(ArrayStruct::class, $renderContext['provider']);
        static::assertNull($renderContext['provider']->get('targetCountries'));
        static::assertSame('DE', $renderContext['provider']->get('storeCountry'));
        static::assertSame('Merchant', $renderContext['provider']->get('sellerName'));
    }

    public function testExtendRenderContextSetsTargetCountriesToNullWhenRepositoryReturnsNoSalesChannel(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->createSalesChannel();
        $salesChannelId = $salesChannel->getId();

        $repository = $this->createSalesChannelRepository([
            /**
             * @return list<SalesChannelEntity>
             */
            static function (Criteria $criteria, Context $repositoryContext) use ($context, $salesChannelId): array {
                static::assertSame([$salesChannelId], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('countries'));
                static::assertSame($context, $repositoryContext);

                return [];
            },
        ]);

        $provider = new OpenAiProductExportProvider($repository);

        $renderContext = $provider->extendRenderContext(
            $this->createProductExport(),
            $this->createSalesChannelContext($salesChannel, $context),
            []
        );

        static::assertInstanceOf(ArrayStruct::class, $renderContext['provider']);
        static::assertNull($renderContext['provider']->get('targetCountries'));
    }

    /**
     * @param list<string|null> $countryIsoCodes
     */
    private function createSalesChannel(array $countryIsoCodes = []): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setName('Merchant');

        if ($countryIsoCodes === []) {
            return $salesChannel;
        }

        $countries = [];
        foreach ($countryIsoCodes as $isoCode) {
            $country = new CountryEntity();
            $country->setId(Uuid::randomHex());
            $country->setIso($isoCode);
            $countries[] = $country;
        }

        $salesChannel->setCountries(new CountryCollection($countries));

        return $salesChannel;
    }

    private function createSalesChannelContext(SalesChannelEntity $salesChannel, ?Context $context = null): SalesChannelContext
    {
        $storeCountry = new CountryEntity();
        $storeCountry->setId(Uuid::randomHex());
        $storeCountry->setIso('DE');

        return Generator::generateSalesChannelContext(
            baseContext: $context ?? Context::createDefaultContext(),
            salesChannel: $salesChannel,
            country: $storeCountry
        );
    }

    private function createProductExport(): ProductExportEntity
    {
        $salesChannelDomain = new SalesChannelDomainEntity();
        $salesChannelDomain->setUrl('https://merchant.example');

        $productExport = new ProductExportEntity();
        $productExport->setSalesChannelDomain($salesChannelDomain);

        return $productExport;
    }

    /**
     * @param array<callable(Criteria, Context): list<SalesChannelEntity>|SalesChannelCollection> $searches
     *
     * @return StaticEntityRepository<SalesChannelCollection>
     */
    private function createSalesChannelRepository(array $searches = []): StaticEntityRepository
    {
        /** @var StaticEntityRepository<SalesChannelCollection> $repository */
        $repository = new StaticEntityRepository($searches);

        return $repository;
    }
}
