<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Service\AbstractDocumentTypeRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(DocumentGenerator::class)]
#[Package('after-sales')]
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

        $mockTypeRenderer = $this->createMock(AbstractDocumentTypeRenderer::class);
        $fileRenderer = new DocumentFileRendererRegistry([$mockTypeRenderer]);

        $generator = new DocumentGenerator(
            $registry,
            $fileRenderer,
            $this->createMock(MediaService::class),
            new StaticEntityRepository([]),
            $this->createMock(Connection::class),
        );

        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('Unable to generate document. Some Error Message.');

        $generator->preview('invoice', $operation, 'deepLinkCode', $context);
    }

    public function testPreviewHtml(): void
    {
        $operation = new DocumentGenerateOperation(
            'orderId',
            HtmlRenderer::FILE_EXTENSION,
            [],
            null,
            false,
            true,
        );

        $context = Context::createDefaultContext();

        $resultRenderer = new RenderedDocument(
            '',
            '',
            'invoice',
            'html',
            [],
            'text/html',
        );
        $resultRenderer->setContent('html');

        $resultRenderer->setTemplateOptions([
            '',
            [
                'order' => new OrderEntity(),
                'context' => $context,
            ],
        ]);

        $result = new RendererResult();
        $result->addSuccess('orderId', $resultRenderer);

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

        $mockTypeRenderer = $this->createMock(AbstractDocumentTypeRenderer::class);
        $mockTypeRenderer->method('getContentType')->willReturn('text/html');
        $mockTypeRenderer->method('render')->willReturn('html');

        $registry = new DocumentRendererRegistry([$mockRenderer]);
        $fileRenderer = new DocumentFileRendererRegistry([$mockTypeRenderer]);

        $generator = new DocumentGenerator(
            $registry,
            $fileRenderer,
            $this->createMock(MediaService::class),
            new StaticEntityRepository([]),
            $this->createMock(Connection::class),
        );

        $document = $generator->preview('invoice', $operation, 'deepLinkCode', $context);

        static::assertSame($document->getContent(), 'html');
        static::assertSame($document->getFileExtension(), 'html');
        static::assertSame($document->getContentType(), 'text/html');
    }
}
