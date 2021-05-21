<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Storefront\Page\Account\Document\DocumentPage;
use Shopware\Storefront\Page\Account\Document\DocumentPageLoadedEvent;
use Shopware\Storefront\Page\Account\Document\DocumentPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class DocumentPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testPageLoadsFailWithoutValidDeepLinkCode(): void
    {
        static::expectException(InvalidDocumentException::class);
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $orderId = $this->placeRandomOrder($context);

        $fileName = 'invoice';

        $document = $this->getContainer()->get(DocumentService::class)->create(
            $orderId,
            'invoice',
            'pdf',
            new DocumentConfiguration(),
            $context->getContext(),
            null,
            true
        );
        $expectedFileContent = 'simple invoice';
        $expectedContentType = 'text/plain';

        $request = new Request([], [], [], [], [], [], $expectedFileContent);
        $request->query->set('fileName', $fileName);
        $request->server->set('HTTP_CONTENT_TYPE', $expectedContentType);
        $request->server->set('HTTP_CONTENT_LENGTH', mb_strlen($expectedFileContent));
        $request->headers->set('content-length', mb_strlen($expectedFileContent));

        $request->query->set('extension', 'txt');

        $documentIdStruct = $this->getContainer()->get(DocumentService::class)->uploadFileForDocument(
            $document->getId(),
            $context->getContext(),
            $request
        );

        /** @var DocumentPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(DocumentPageLoadedEvent::class, $event);

        $request->attributes->set('documentId', $documentIdStruct->getId());
        $request->attributes->set('deepLinkCode', Random::getAlphanumericString(32));
        $this->getPageLoader()->load($request, $context);
    }

    public function testPageLoadsSuccess(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $orderId = $this->placeRandomOrder($context);

        $fileName = 'invoice';

        $document = $this->getContainer()->get(DocumentService::class)->create(
            $orderId,
            'invoice',
            'pdf',
            new DocumentConfiguration(),
            $context->getContext(),
            null,
            true
        );
        $expectedFileContent = 'simple invoice';
        $expectedContentType = 'text/plain';

        $request = new Request([], [], [], [], [], [], $expectedFileContent);
        $request->query->set('fileName', $fileName);
        $request->server->set('HTTP_CONTENT_TYPE', $expectedContentType);
        $request->server->set('HTTP_CONTENT_LENGTH', mb_strlen($expectedFileContent));
        $request->headers->set('content-length', mb_strlen($expectedFileContent));

        $request->query->set('extension', 'txt');

        $documentIdStruct = $this->getContainer()->get(DocumentService::class)->uploadFileForDocument(
            $document->getId(),
            $context->getContext(),
            $request
        );

        /** @var DocumentPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(DocumentPageLoadedEvent::class, $event);

        $request->attributes->set('documentId', $documentIdStruct->getId());
        $request->attributes->set('deepLinkCode', $documentIdStruct->getDeepLinkCode());
        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(DocumentPage::class, $page);
        static::assertEquals($fileName . '.txt', $page->getDocument()->getFilename());
        static::assertEquals($expectedFileContent, $page->getDocument()->getFileBlob());
        static::assertEquals($expectedContentType, $page->getDocument()->getContentType());
        self::assertPageEvent(DocumentPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return DocumentPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(DocumentPageLoader::class);
    }
}
