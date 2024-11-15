<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use Dompdf\Cpdf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(DocumentGenerator::class)]
#[Package('checkout')]
class DocumentGeneratorTest extends TestCase
{
    public function testPreviewErrorThrowsDocumentException(): void
    {
        $operation = new DocumentGenerateOperation(
            'orderId',
            FileTypes::PDF,
            [],
            null,
            false,
            true
        );
        $context = Context::createDefaultContext();

        $result = new RendererResult();
        $result->addError('orderId', new \Exception('Some Error Message.'));

        $mockRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $mockRenderer->method('supports')->willReturn('invoice');
        $mockRenderer
            ->expects(static::once())
            ->method('render')
            ->with(
                ['orderId' => $operation],
                $context,
                static::callback(fn (DocumentRendererConfig $config): bool => $config->deepLinkCode === 'deepLinkCode')
            )
            ->willReturn($result);

        $registry = new DocumentRendererRegistry([$mockRenderer]);
        $generator = new DocumentGenerator(
            $registry,
            new PdfRenderer([], new ExtensionDispatcher(new EventDispatcher())),
            $this->createMock(MediaService::class),
            new StaticEntityRepository([]),
            $this->createMock(Connection::class),
        );

        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('Unable to generate document. Some Error Message.');

        $generator->preview('invoice', $operation, 'deepLinkCode', $context);
    }

    public function testPreviewPDF(): void
    {
        $orderId = Uuid::randomHex();

        $resultXML = new RendererResult();
        $resultXML->addSuccess($orderId, new RenderedDocument(fileExtension: FileTypes::XML));
        $resultPDF = new RendererResult();
        $resultPDF->addSuccess($orderId, new RenderedDocument());

        $mockRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $mockRenderer
            ->method('supports')
            ->willReturn('invoice');
        $mockRenderer
            ->expects(static::exactly(2))
            ->method('render')
            ->willReturn($resultXML, $resultPDF);

        $registry = new DocumentRendererRegistry([$mockRenderer]);
        $generator = new DocumentGenerator(
            $registry,
            new PdfRenderer([], new ExtensionDispatcher($this->createMock(EventDispatcherInterface::class))),
            $this->createMock(MediaService::class),
            new StaticEntityRepository([]),
            $this->createMock(Connection::class),
        );

        $operation = new DocumentGenerateOperation($orderId, FileTypes::XML);
        $renderResultXML = $generator->preview('invoice', $operation, '', Context::createDefaultContext());

        $operation = new DocumentGenerateOperation($orderId);
        $renderResultPDF = $generator->preview('invoice', $operation, '', Context::createDefaultContext());
        $pdfVersion = Cpdf::PDF_VERSION;

        static::assertEmpty($renderResultXML->getContent());
        static::assertMatchesRegularExpression("/^%PDF-$pdfVersion/", $renderResultPDF->getContent());
    }
}
