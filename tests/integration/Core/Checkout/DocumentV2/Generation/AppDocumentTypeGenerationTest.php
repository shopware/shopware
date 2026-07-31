<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
class AppDocumentTypeGenerationTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DocumentV2Trait;

    private const IDENTIFIER = 'swag_certificate';

    private const ZUGFERD_XML_IDENTIFIER = 'swag_zugferd_certificate';

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $shippingAddressId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(
                    ['defaultShippingAddressId' => $shippingAddressId],
                    $this->buildDemoShippingAddress($shippingAddressId),
                ),
            ],
        );

        static::getContainer()->get(SystemConfigService::class)->setMultiple([
            'core.basicInformation.companyName' => 'Example Company',
            'core.basicInformation.companyStreet' => 'Example Street 1',
            'core.basicInformation.companyZipcode' => '12345',
            'core.basicInformation.companyCity' => 'Example City',
            'core.basicInformation.companyCountryId' => $this->loadCompanyCountry()->getId(),
        ]);
    }

    public function testInstallingAppRegisteredDocumentTypeGeneratesAndPersistsPdf(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/apps/withDocumentGeneration');

        $formats = static::getContainer()->get(DocumentTypeRegistry::class)->getSupportedFormats(self::IDENTIFIER);
        static::assertEqualsCanonicalizing(
            [DocumentFormat::HTML->value, DocumentFormat::PDF->value],
            $formats,
        );

        $bundle = static::getContainer()->get(DocumentConfigLoader::class)->load(
            self::IDENTIFIER,
            $this->salesChannelContext->getSalesChannelId(),
            $this->context,
        );

        static::assertSame('a5', $bundle->config->pageSize);
        static::assertSame('landscape', $bundle->config->pageOrientation);
        static::assertSame(20, $bundle->config->itemsPerPage);
        static::assertTrue($bundle->display->displayHeader);
        static::assertTrue($bundle->display->displayFooter);
        static::assertTrue($bundle->display->displayPageCount);
        static::assertTrue($bundle->display->displayLineItems);
        static::assertTrue($bundle->display->displayPrices);

        $orderId = $this->createDraftOrder();

        $document = static::getContainer()->get(DocumentGenerator::class)->generate(
            new DocumentGenerationRequest($orderId, self::IDENTIFIER, [DocumentFormat::PDF->value]),
            $this->context,
        );

        static::assertInstanceOf(DocumentEntity::class, $document);
        static::assertNull($document->getDocumentTypeId());
        static::assertSame(self::IDENTIFIER, $document->getConfig()['documentType'] ?? null);

        $documentFiles = $document->getDocumentFiles();
        static::assertNotNull($documentFiles);
        static::assertCount(1, $documentFiles);

        $file = $documentFiles->first();
        static::assertNotNull($file);
        static::assertSame(DocumentFormat::PDF->value, $file->getDocumentFormat());
        static::assertNotSame('', $file->getMediaId());
        static::assertTrue(Uuid::isValid($file->getMediaId()));

        $media = $file->getMedia();
        static::assertSame(DocumentFormat::PDF->mimeType(), $media->getMimeType());
    }

    public function testInstallingAppRegisteredDocumentTypeGeneratesAndPersistsZugferdXml(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/apps/withDocumentGeneration');

        $formats = static::getContainer()->get(DocumentTypeRegistry::class)->getSupportedFormats(self::ZUGFERD_XML_IDENTIFIER);
        static::assertSame([DocumentFormat::ZUGFERD_XML->value], $formats);

        $orderId = $this->createDraftOrder();

        $document = static::getContainer()->get(DocumentGenerator::class)->generate(
            new DocumentGenerationRequest($orderId, self::ZUGFERD_XML_IDENTIFIER, [DocumentFormat::ZUGFERD_XML->value]),
            $this->context,
        );

        static::assertInstanceOf(DocumentEntity::class, $document);
        static::assertNull($document->getDocumentTypeId());
        static::assertSame(self::ZUGFERD_XML_IDENTIFIER, $document->getConfig()['documentType'] ?? null);

        $documentFiles = $document->getDocumentFiles();
        static::assertNotNull($documentFiles);
        static::assertCount(1, $documentFiles);

        $file = $documentFiles->first();
        static::assertNotNull($file);
        static::assertSame(DocumentFormat::ZUGFERD_XML->value, $file->getDocumentFormat());
        static::assertNotSame('', $file->getMediaId());
        static::assertTrue(Uuid::isValid($file->getMediaId()));

        $media = $file->getMedia();
        static::assertSame(DocumentFormat::ZUGFERD_XML->mimeType(), $media->getMimeType());
    }

    private function createDraftOrder(): string
    {
        $cart = $this->generateDemoCartWithTaxes([19, 7]);
        $orderId = $this->persistCart($cart);
        $this->enrichOrderForRendering($orderId);

        return $orderId;
    }
}
