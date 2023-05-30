<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Content\Test\Flow\OrderActionTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group store-api
 *
 * @internal
 */
#[Package('customer-order')]
class DocumentRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderActionTrait, CustomerTestTrait {
        OrderActionTrait::login insteadof CustomerTestTrait;
    }

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private DocumentGenerator $documentGenerator;

    private string $customerId;

    private string $guestId;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
        $this->getContainer()->get(DocumentConfigLoader::class)->reset();
        $this->customerId = $this->createCustomer();
        $this->guestId = $this->createCustomer('shopware@123', 'guest@example.com', true);
        $this->createOrder($this->customerId);
    }

    /**
     * @dataProvider documentDownloadRouteDataProvider
     */
    public function testDownload(bool $isGuest, ?bool $withValidDeepLinkCode, \Closure $assertionCallback): void
    {
        $token = $this->getLoggedInContextToken($isGuest ? $this->guestId : $this->customerId, $this->ids->get('sales-channel'));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $token);

        $operation = new DocumentGenerateOperation($this->ids->get('order'));
        $document = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$operation->getOrderId() => $operation], Context::createDefaultContext())->getSuccess()->first();
        static::assertInstanceOf(DocumentIdStruct::class, $document);
        $deepLinkCode = '';

        if ($withValidDeepLinkCode !== null) {
            $deepLinkCode = $withValidDeepLinkCode ? $document->getDeepLinkCode() : Uuid::randomHex();
        }

        $endpoint = \sprintf('/store-api/document/download/%s', $document->getId());

        if ($deepLinkCode !== '') {
            $endpoint .= '/' . $deepLinkCode;
        }

        $this->browser
            ->request(
                'GET',
                $endpoint,
                [
                ]
            );

        $response = $this->browser->getResponse();
        static::assertNotNull($this->browser->getResponse());

        $assertionCallback($response);
    }

    public static function documentDownloadRouteDataProvider(): \Generator
    {
        yield 'guest with valid deep link code' => [
            true,
            true,
            function (Response $response): void {
                $headers = $response->headers;

                static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
                static::assertNotEmpty($response->getContent());
                static::assertEquals('inline; filename=invoice_1000.pdf', $headers->get('content-disposition'));
                static::assertEquals('application/pdf', $headers->get('content-type'));
            },
        ];
        yield 'guest with invalid deep link code' => [
            true,
            false,
            function (Response $response): void {
                static::assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
                $response = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
                static::assertArrayHasKey('errors', $response);
                static::assertSame('DOCUMENT__INVALID_DOCUMENT_ID', $response['errors'][0]['code']);
            },
        ];
        yield 'guest without deep link code' => [
            true,
            null,
            function (Response $response): void {
                static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
                $response = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
                static::assertArrayHasKey('errors', $response);
                static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
            },
        ];
        yield 'customer with deep valid link code' => [
            false,
            true,
            function (Response $response): void {
                $headers = $response->headers;

                static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
                static::assertNotEmpty($response->getContent());
                static::assertEquals('inline; filename=invoice_1000.pdf', $headers->get('content-disposition'));
                static::assertEquals('application/pdf', $headers->get('content-type'));
            },
        ];
        yield 'customer with invalid deep link code' => [
            false,
            false,
            function (Response $response): void {
                static::assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
                $response = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
                static::assertArrayHasKey('errors', $response);
                static::assertSame('DOCUMENT__INVALID_DOCUMENT_ID', $response['errors'][0]['code']);
            },
        ];
        yield 'customer without deep link code' => [
            false,
            null,
            function (Response $response): void {
                $headers = $response->headers;

                static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
                static::assertNotEmpty($response->getContent());
                static::assertEquals('inline; filename=invoice_1000.pdf', $headers->get('content-disposition'));
                static::assertEquals('application/pdf', $headers->get('content-type'));
            },
        ];
    }
}
