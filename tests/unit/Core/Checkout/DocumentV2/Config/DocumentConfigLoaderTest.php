<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentConfigLoader::class)]
class DocumentConfigLoaderTest extends TestCase
{
    private const COMPANY_COUNTRY_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c0';

    public function testLoadPicksMatchingSalesChannelRowWhenMultipleNonGlobalRowsReturned(): void
    {
        $matchingSalesChannelId = Uuid::randomHex();

        $globalRow = $this->buildBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
        );

        $matchingRow = $this->buildBaseConfig(
            global: false,
            pageSize: 'Letter',
            companyName: 'Matching Channel GmbH',
            salesChannelId: $matchingSalesChannelId,
        );

        $otherRow = $this->buildBaseConfig(
            global: false,
            pageSize: 'A5',
            companyName: 'Wrong Channel GmbH',
        );

        /** @var StaticEntityRepository<DocumentBaseConfigCollection> $documentRepo */
        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $otherRow, $matchingRow])],
            new DocumentBaseConfigDefinition(),
        );

        /** @var StaticEntityRepository<CountryCollection> $countryRepo */
        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->buildCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader($documentRepo, $countryRepo);

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $matchingSalesChannelId,
            Context::createDefaultContext(),
        );

        static::assertSame('Letter', $bundle->config->pageSize);
        static::assertSame('Matching Channel GmbH', $bundle->company->companyName);
    }

    public function testLoadFallsBackToGlobalWhenNoSalesChannelRowMatches(): void
    {
        $globalRow = $this->buildBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
        );

        $unrelatedRow = $this->buildBaseConfig(
            global: false,
            pageSize: 'A5',
            companyName: 'Unrelated GmbH',
        );

        /** @var StaticEntityRepository<DocumentBaseConfigCollection> $documentRepo */
        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $unrelatedRow])],
            new DocumentBaseConfigDefinition(),
        );

        /** @var StaticEntityRepository<CountryCollection> $countryRepo */
        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->buildCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader($documentRepo, $countryRepo);

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );

        static::assertSame('A4', $bundle->config->pageSize);
        static::assertSame('Global GmbH', $bundle->company->companyName);
    }

    public function testLoadRejectsZeroItemsPerPage(): void
    {
        $globalRow = $this->buildBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
            itemsPerPage: 0,
        );

        /** @var StaticEntityRepository<DocumentBaseConfigCollection> $documentRepo */
        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow])],
            new DocumentBaseConfigDefinition(),
        );

        /** @var StaticEntityRepository<CountryCollection> $countryRepo */
        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->buildCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader($documentRepo, $countryRepo);

        static::expectException(DocumentV2Exception::class);
        static::expectExceptionMessageMatches('/itemsPerPage/');

        $loader->load(
            DocumentType::INVOICE->value,
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );
    }

    private function buildBaseConfig(
        bool $global,
        string $pageSize,
        string $companyName,
        ?string $salesChannelId = null,
        int $itemsPerPage = 10,
    ): DocumentBaseConfigEntity {
        $entity = new DocumentBaseConfigEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setId(Uuid::randomHex());
        $entity->setGlobal($global);
        $entity->setPageSize($pageSize);
        $entity->setPageOrientation('portrait');
        $entity->setItemsPerPage($itemsPerPage);
        $entity->setConfig([
            'companyName' => $companyName,
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
            'companyCountryId' => self::COMPANY_COUNTRY_ID,
        ]);

        if (!$global && $salesChannelId !== null) {
            $assignment = new DocumentBaseConfigSalesChannelEntity();
            $assignment->setUniqueIdentifier(Uuid::randomHex());
            $assignment->setId(Uuid::randomHex());
            $assignment->setSalesChannelId($salesChannelId);

            $entity->setSalesChannels(new DocumentBaseConfigSalesChannelCollection([$assignment]));
        } else {
            $entity->setSalesChannels(new DocumentBaseConfigSalesChannelCollection());
        }

        return $entity;
    }

    private function buildCountry(): CountryEntity
    {
        $country = new CountryEntity();
        $country->setUniqueIdentifier(self::COMPANY_COUNTRY_ID);
        $country->setId(self::COMPANY_COUNTRY_ID);

        return $country;
    }
}
