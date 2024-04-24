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
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

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
            new PdfRenderer([]),
            $this->createMock(MediaService::class),
            new StaticEntityRepository([]),
            $this->createMock(Connection::class),
        );

        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('Unable to generate document. Some Error Message.');

        $generator->preview('invoice', $operation, 'deepLinkCode', $context);
    }
}
