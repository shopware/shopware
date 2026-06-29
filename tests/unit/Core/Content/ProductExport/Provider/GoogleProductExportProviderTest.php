<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\GoogleProductExportProvider;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GoogleProductExportProvider::class)]
class GoogleProductExportProviderTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetTechnicalNameReturnsGoogle(): void
    {
        $provider = new GoogleProductExportProvider(
            $this->createSalesChannelRepository(),
            $this->createMock(SystemConfigService::class)
        );

        static::assertSame('google', $provider->getTechnicalName());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextUsesCountriesFromSalesChannelContext(): void
    {
        $repository = $this->createSalesChannelRepository();
        $salesChannel = $this->createSalesChannel(['DE', null, 'FR']);
        $productExport = $this->createProductExport($salesChannel->getId());
        $provider = new GoogleProductExportProvider(
            $repository,
            $this->createSystemConfigService([], $salesChannel->getId())
        );

        $renderContext = $provider->extendRenderContext(
            $productExport,
            $this->createSalesChannelContext($salesChannel),
            ['existing' => 'value']
        );

        static::assertSame('value', $renderContext['existing']);
        static::assertInstanceOf(ArrayStruct::class, $renderContext['provider']);

        $providerContext = $renderContext['provider'];

        static::assertSame('google', $providerContext->get('name'));
        static::assertSame('DE', $providerContext->get('storeCountry'));
        static::assertSame(['DE', 'FR'], $providerContext->get('targetCountries'));
        static::assertSame('Merchant', $providerContext->get('sellerName'));
        static::assertSame('https://merchant.example', $providerContext->get('sellerUrl'));
        static::assertSame('DE', $providerContext->get('shippingCountry'));
        static::assertSame('Generated Shipping', $providerContext->get('shippingService'));
        static::assertSame([
            'color' => null,
            'size' => null,
            'size_system' => null,
            'gender' => null,
            'age_group' => null,
            'material' => null,
            'condition' => null,
            'custom_variants' => null,
        ], $providerContext->get('variantMapping'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextLoadsCountriesFromRepositoryWhenAssociationIsNotLoaded(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->createSalesChannel();
        $salesChannelId = $salesChannel->getId();
        $fallbackSalesChannel = $this->createSalesChannel([null, 'US']);
        $productExport = $this->createProductExport($salesChannelId);

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

        $provider = new GoogleProductExportProvider(
            $repository,
            $this->createSystemConfigService([], $salesChannelId)
        );

        $renderContext = $provider->extendRenderContext(
            $productExport,
            $this->createSalesChannelContext($salesChannel, $context),
            []
        );

        static::assertInstanceOf(ArrayStruct::class, $renderContext['provider']);
        static::assertSame(['US'], $renderContext['provider']->get('targetCountries'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextSetsTargetCountriesToNullWhenTheyCannotBeResolved(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->createSalesChannel();
        $salesChannelId = $salesChannel->getId();
        $fallbackSalesChannel = $this->createSalesChannel();
        $productExport = $this->createProductExport($salesChannelId);

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

        $provider = new GoogleProductExportProvider(
            $repository,
            $this->createSystemConfigService([], $salesChannelId)
        );

        $renderContext = $provider->extendRenderContext(
            $productExport,
            $this->createSalesChannelContext($salesChannel, $context),
            []
        );

        static::assertInstanceOf(ArrayStruct::class, $renderContext['provider']);
        static::assertNull($renderContext['provider']->get('targetCountries'));
        static::assertSame('DE', $renderContext['provider']->get('storeCountry'));
        static::assertSame('Merchant', $renderContext['provider']->get('sellerName'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextUsesConfiguredInputValues(): void
    {
        $salesChannel = $this->createSalesChannel(['DE']);
        $salesChannelId = $salesChannel->getId();
        $productExport = $this->createProductExport($salesChannelId);

        $provider = new GoogleProductExportProvider(
            $this->createSalesChannelRepository(),
            $this->createSystemConfigService([
                'core.googleProductExport.variantColor' => [' color ', '', 5, 'secondary-color'],
                'core.googleProductExport.variantSize' => [],
                'core.googleProductExport.variantSizeSystem' => ['eu_size'],
                'core.googleProductExport.variantGender' => ['unisex'],
                'core.googleProductExport.variantAgeGroup' => ['adult'],
                'core.googleProductExport.variantMaterial' => ['cotton'],
                'core.googleProductExport.variantCondition' => ['refurbished'],
                'core.googleProductExport.variantCustom' => ['custom_a', null, 'custom_b'],
            ], $salesChannelId)
        );

        $renderContext = $provider->extendRenderContext(
            $productExport,
            $this->createSalesChannelContext($salesChannel),
            []
        );

        static::assertInstanceOf(ArrayStruct::class, $renderContext['provider']);
        static::assertSame([
            'color' => [' color ', 'secondary-color'],
            'size' => null,
            'size_system' => ['eu_size'],
            'gender' => ['unisex'],
            'age_group' => ['adult'],
            'material' => ['cotton'],
            'condition' => ['refurbished'],
            'custom_variants' => ['custom_a', 'custom_b'],
        ], $renderContext['provider']->get('variantMapping'));
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

    private function createProductExport(?string $salesChannelId = null): ProductExportEntity
    {
        $salesChannelDomain = new SalesChannelDomainEntity();
        $salesChannelDomain->setUrl('https://merchant.example');

        $productExport = new ProductExportEntity();
        $productExport->setSalesChannelDomain($salesChannelDomain);
        $productExport->setSalesChannelId($salesChannelId ?? Uuid::randomHex());

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

    /**
     * @param array<string, mixed> $config
     *
     * @return SystemConfigService&MockObject
     */
    private function createSystemConfigService(array $config, ?string $expectedSalesChannelId): SystemConfigService
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getDomain')
            ->with('core.googleProductExport', $expectedSalesChannelId, true)
            ->willReturn($config);

        return $systemConfigService;
    }
}
