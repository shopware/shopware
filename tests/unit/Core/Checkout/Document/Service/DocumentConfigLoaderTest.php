<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentConfigLoader::class)]
class DocumentConfigLoaderTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testLoad(): void
    {
        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('documentType.technicalName', 'invoice'));
        $expectedCriteria->addAssociation('logo');
        $expectedCriteria->getAssociation('salesChannels')->addFilter(new EqualsFilter('salesChannelId', $this->ids->get('sales-channel-id')));

        $context = Context::createDefaultContext();
        $countryId = Uuid::randomHex();

        $documentSalesChannel = new DocumentBaseConfigSalesChannelEntity();
        $documentSalesChannel->setUniqueIdentifier($this->ids->get('document-sales-channel'));
        $documentSalesChannel->setSalesChannelId($this->ids->get('sales-channel-id'));

        $document1 = new DocumentBaseConfigEntity();
        $document1->setId($this->ids->get('document-1'));
        $document1->setUniqueIdentifier($this->ids->get('document-1'));
        $document1->setGlobal(true);
        $document1->setSalesChannels(new DocumentBaseConfigSalesChannelCollection([$documentSalesChannel]));
        $document1->setConfig(['companyCountryId' => $countryId]);

        $document2 = new DocumentBaseConfigEntity();
        $document2->setId($this->ids->get('document-2'));
        $document2->setUniqueIdentifier($this->ids->get('document-2'));
        $document2->setGlobal(false);
        $document2->setSalesChannels(new DocumentBaseConfigSalesChannelCollection([$documentSalesChannel]));

        $result = new EntitySearchResult(
            'document_base_config',
            2,
            new DocumentBaseConfigCollection([$document1, $document2]),
            null,
            $expectedCriteria,
            $context
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $countryRepo = $this->createMock(EntityRepository::class);
        $countryRepo->expects($this->once())
            ->method('search')
            ->with(static::equalTo(new Criteria([$countryId])), $context);

        $loader = new DocumentConfigLoader($repo, $countryRepo);
        $config = $loader->load('invoice', $this->ids->get('sales-channel-id'), $context);

        $salesChannels = $config->getVars()['salesChannels'] ?? null;

        static::assertInstanceOf(DocumentBaseConfigSalesChannelCollection::class, $salesChannels);
        static::assertCount(1, $salesChannels);

        $salesChannel = $salesChannels->first();

        static::assertInstanceOf(DocumentBaseConfigSalesChannelEntity::class, $salesChannel);
        static::assertSame($this->ids->get('document-sales-channel'), $salesChannel->getUniqueIdentifier());
    }

    /**
     * @param list<string> $activeFeatures
     */
    #[DataProvider('logoUrlProvider')]
    public function testLoadNormalizesLogoUrl(?string $logoUrl, array $activeFeatures, ?string $expectedLogoUrl): void
    {
        $document = new DocumentBaseConfigEntity();
        $document->setId($this->ids->get('document'));
        $document->setGlobal(true);

        if ($logoUrl !== null) {
            $logo = new MediaEntity();

            $logo->setId($this->ids->get('logo'));
            $logo->setUrl($logoUrl);

            $document->setLogo($logo);
        }

        $context = Context::createDefaultContext();

        /** @var StaticEntityRepository<DocumentBaseConfigCollection> $configRepository */
        $configRepository = new StaticEntityRepository(
            [new EntitySearchResult(
                'document_base_config',
                1,
                new DocumentBaseConfigCollection([$document]),
                null,
                new Criteria(),
                $context
            )],
            new DocumentBaseConfigDefinition(),
        );

        Feature::fake($activeFeatures, function () use ($configRepository, $context, $expectedLogoUrl): void {
            /** @var StaticEntityRepository<CountryCollection> $countryRepository */
            $countryRepository = new StaticEntityRepository([], new CountryDefinition());

            $loader = new DocumentConfigLoader(
                $configRepository,
                $countryRepository,
            );

            $config = $loader->load('invoice', $this->ids->get('sales-channel-id'), $context);
            $cachedConfig = $loader->load('invoice', $this->ids->get('sales-channel-id'), $context);

            static::assertSame($expectedLogoUrl, $config->getLogo()?->getUrl());
            static::assertSame($expectedLogoUrl, $cachedConfig->getLogo()?->getUrl());
        });
    }

    /**
     * @return iterable<string, array{logoUrl: string|null, activeFeatures: list<string>, expectedLogoUrl: string|null}>
     */
    public static function logoUrlProvider(): iterable
    {
        yield 'encodes raw logo url' => [
            'logoUrl' => 'https://example.com/media/my logo_test.webp',
            'activeFeatures' => [],
            'expectedLogoUrl' => 'https://example.com/media/my%20logo_test.webp',
        ];

        yield 'keeps logo url untouched' => [
            'logoUrl' => 'https://example.com/media/my logo_test.webp',
            'activeFeatures' => ['v6.8.0.0'],
            'expectedLogoUrl' => 'https://example.com/media/my logo_test.webp',
        ];

        yield 'empty logo url is kept' => [
            'logoUrl' => '',
            'activeFeatures' => [],
            'expectedLogoUrl' => '',
        ];

        yield 'missing logo is ignored' => [
            'logoUrl' => null,
            'activeFeatures' => [],
            'expectedLogoUrl' => null,
        ];
    }
}
