<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentMetaProvider::class)]
class DocumentMetaProviderTest extends TestCase
{
    private const COMPANY_COUNTRY_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c0';

    public function testKeyIsMeta(): void
    {
        static::assertSame('meta', $this->createProvider()->getKey());
    }

    #[DataProvider('documentTypeProvider')]
    public function testSupportsEveryDocumentType(string $documentType): void
    {
        static::assertTrue($this->createProvider()->supports($documentType));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function documentTypeProvider(): \Generator
    {
        yield 'core invoice' => [DocumentType::INVOICE->value];
        yield 'core delivery note' => [DocumentType::DELIVERY_NOTE->value];
        yield 'core credit note' => [DocumentType::CREDIT_NOTE->value];
        yield 'core cancellation invoice' => [DocumentType::CANCELLATION_INVOICE->value];
        yield 'plugin-defined type unknown to the core' => ['my_plugin_document'];
    }

    public function testProvideRenderingDataBuildsMetaFromConfigBundleAndRequest(): void
    {
        $provider = $this->createProvider(['companyEmail' => 'shop@example.com']);

        $request = new DocumentGenerationRequest(
            $this->createOrder()->getId(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            '12345',
            documentComment: 'thanks for your order',
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        $meta = $provider->provideRenderingData(new ProviderInput($this->createOrder(), $request), Context::createDefaultContext());

        static::assertSame('2026-05-05T12:00:00+00:00', $meta->documentDate);
        static::assertSame('12345', $meta->documentNumber);
        static::assertSame('thanks for your order', $meta->documentComment);
        static::assertSame('A4', $meta->config->pageSize);
        static::assertSame('Example', $meta->company->companyName);
        static::assertSame('shop@example.com', $meta->company->companyEmail);
        static::assertSame('shop@example.com', $meta->legacyConfig['companyEmail']);
    }

    public function testProvideRenderingDataThrowsWhenDocumentNumberMissing(): void
    {
        $provider = $this->createProvider();

        $request = new DocumentGenerationRequest(
            $this->createOrder()->getId(),
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        $this->expectExceptionObject(DocumentV2Exception::missingDocumentNumber(DocumentType::INVOICE->value));

        $provider->provideRenderingData(new ProviderInput($this->createOrder(), $request), Context::createDefaultContext());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createProvider(array $config = []): DocumentMetaProvider
    {
        $companyCountry = new CountryEntity();
        $companyCountry->setUniqueIdentifier(self::COMPANY_COUNTRY_ID);
        $companyCountry->setId(self::COMPANY_COUNTRY_ID);

        $countryRepository = new StaticEntityRepository(
            [new CountryCollection([$companyCountry])],
            new CountryDefinition(),
        );

        $baseConfig = new DocumentBaseConfigEntity();
        $baseConfig->setUniqueIdentifier(Uuid::randomHex());
        $baseConfig->setId(Uuid::randomHex());
        $baseConfig->setGlobal(true);
        $baseConfig->setPageSize('A4');
        $baseConfig->setPageOrientation('portrait');
        $baseConfig->setItemsPerPage(10);
        $baseConfig->setConfig([
            'companyName' => 'Example',
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
            'companyCountryId' => self::COMPANY_COUNTRY_ID,
            ...$config,
        ]);

        $documentConfigRepository = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$baseConfig])],
            new DocumentBaseConfigDefinition(),
        );

        $mediaRepository = new StaticEntityRepository(
            [new MediaCollection([])],
            new MediaDefinition(),
        );

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([]);

        return new DocumentMetaProvider(
            new DocumentConfigLoader(
                $documentConfigRepository,
                $countryRepository,
                $mediaRepository,
                static::createStub(SystemConfigService::class),
                new DocumentTypeRegistry([], $storage),
            ),
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());

        return $order;
    }
}
