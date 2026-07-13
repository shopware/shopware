<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentV2ControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DocumentV2Trait;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<DocumentFileCollection>
     */
    private EntityRepository $documentFileRepository;

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

        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->documentFileRepository = static::getContainer()->get('document_file.repository');
    }

    public function testAvailableTypesReturnsImplementedDocumentTypesAndFormats(): void
    {
        $this->getBrowser()->jsonRequest('GET', '/api/_action/order/document-v2/available-types');

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload['documentTypes'] ?? null);
        static::assertArrayHasKey(DocumentType::INVOICE->value, $payload['documentTypes']);
        static::assertArrayNotHasKey(DocumentType::DELIVERY_NOTE->value, $payload['documentTypes']);
        static::assertEqualsCanonicalizing(
            [
                DocumentFormat::HTML->value,
                DocumentFormat::ZUGFERD_XML->value,
                DocumentFormat::PDF->value,
            ],
            $payload['documentTypes'][DocumentType::INVOICE->value]['formats'] ?? null,
        );
    }

    public function testPreviewRendersRequestedFormat(): void
    {
        $this->seedDemoInvoiceBaseConfig();

        [$orderId, $orderVersionId] = $this->createDraftOrder();

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/preview',
            [
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'documentType' => DocumentType::INVOICE->value,
                'fileType' => DocumentFormat::HTML->value,
                'documentNumber' => self::DOCUMENT_NUMBER,
                'documentDate' => self::DOCUMENT_DATE,
                'documentComment' => 'comment.',
            ],
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringStartsWith(DocumentFormat::HTML->mimeType(), (string) $response->headers->get('content-type'));
        static::assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));

        $content = (string) $response->getContent();
        static::assertStringContainsString('<html', $content);
        static::assertStringContainsString(self::DOCUMENT_NUMBER, $content);
    }

    public function testCreateReturnsDocumentReference(): void
    {
        $this->seedDemoInvoiceBaseConfig();

        [$orderId, $orderVersionId] = $this->createDraftOrder();

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/create',
            [
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'documentType' => DocumentType::INVOICE->value,
                'fileTypes' => [
                    DocumentFormat::HTML->value,
                ],
                'documentNumber' => '1001-' . Uuid::randomHex(),
                'documentDate' => self::DOCUMENT_DATE,
            ],
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($payload['documentId'] ?? null);
        static::assertTrue(Uuid::isValid($payload['documentId']));
        static::assertIsString($payload['documentDeepLink'] ?? null);
        static::assertNotSame('', $payload['documentDeepLink']);
        static::assertSame([DocumentFormat::HTML->value], $payload['fileTypes'] ?? null);

        $files = $this->loadDocumentFiles($payload['documentId']);
        static::assertCount(1, $files);

        $file = $files->first();
        static::assertInstanceOf(DocumentFileEntity::class, $file);
        static::assertSame(DocumentFormat::HTML->value, $file->getDocumentFormat());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createDraftOrder(): array
    {
        $cart = $this->generateDemoCartWithTaxes([19, 7]);
        $cart = $this->applyTenPercentPromotion($cart);
        $orderId = $this->persistCart($cart);
        $this->enrichOrderForRendering($orderId);

        return [
            $orderId,
            $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT'),
        ];
    }

    private function loadDocumentFiles(string $documentId): DocumentFileCollection
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('documentId', $documentId));

        return $this->documentFileRepository->search($criteria, $this->context)->getEntities();
    }
}
