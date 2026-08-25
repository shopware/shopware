<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentPersisterCompatTest extends TestCase
{
    use DocumentV2Trait;

    private DocumentGenerator $documentGenerator;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('DOCUMENT_GENERATION_REWORK', $this);

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

        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Translator::class)->reset();

        parent::tearDown();
    }

    public function testGeneratedDocumentDualWritesTypeNameAndFillsLegacyMediaSlots(): void
    {
        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19]));
        $this->enrichOrderForRendering($orderId);
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);

        $document = $this->documentGenerator->generate(
            new DocumentGenerationRequest(
                $orderId,
                DocumentType::INVOICE,
                [DocumentFormat::PDF, DocumentFormat::HTML],
                '1001',
                documentDate: self::DOCUMENT_DATE,
            ),
            $this->context,
        );

        static::assertSame('invoice', $document->getTypeName());
        static::assertSame('1001', $document->getDocumentNumber());

        $mediaByFormat = [];

        foreach ($document->getDocumentFiles() ?? [] as $file) {
            $mediaByFormat[$file->getDocumentFormat()] = $file->getMediaId();
        }

        static::assertArrayHasKey(DocumentFormat::PDF->value, $mediaByFormat);
        static::assertArrayHasKey(DocumentFormat::HTML->value, $mediaByFormat);

        static::assertSame($mediaByFormat[DocumentFormat::PDF->value], $document->getDocumentMediaFileId());
        static::assertSame($mediaByFormat[DocumentFormat::HTML->value], $document->getDocumentA11yMediaFileId());
    }

    public function testTypeNameIsBackfilledOnLegacyStyleDocumentWrite(): void
    {
        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19]));

        $documentTypeId = static::getContainer()->get('document_type.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('technicalName', DocumentType::INVOICE->value)), $this->context)
            ->firstId();
        static::assertIsString($documentTypeId);

        $id = Uuid::randomHex();

        static::getContainer()->get('document.repository')->create([[
            'id' => $id,
            'orderId' => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'documentTypeId' => $documentTypeId,
            'config' => ['documentNumber' => '2001'],
            'deepLinkCode' => Random::getAlphanumericString(32),
            'static' => false,
            'sent' => false,
        ]], $this->context);

        $document = static::getContainer()->get('document.repository')
            ->search(new Criteria([$id]), $this->context)->getEntities()->first();

        static::assertInstanceOf(DocumentEntity::class, $document);
        static::assertSame('invoice', $document->getTypeName());
    }
}
