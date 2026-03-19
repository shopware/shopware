<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Service\ZugferdEmbeddedService;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdEmbeddedService::class)]
class ZugferdEmbeddedServiceTest extends TestCase
{
    private ZugferdEmbeddedService $service;

    protected function setUp(): void
    {
        $this->service = new ZugferdEmbeddedService();
    }

    public function testEmbedWithFullSuccess(): void
    {
        $baseDocument = new RendererResult();
        $baseDocument->addSuccess('order1', new RenderedDocument(content: $this->getPDFContent()));

        $electronicDocument = new RendererResult();
        $electronicDocument->addSuccess('order1', new RenderedDocument(content: $this->getXMLContent()));

        $result = $this->service->embed(
            ['order1' => new DocumentGenerateOperation('order1')],
            Context::createDefaultContext(),
            new DocumentRendererConfig(),
            $baseDocument,
            $this->createElectronicRendererMock($electronicDocument),
            'version'
        );

        static::assertInstanceOf(RenderedDocument::class, $result->getOrderSuccess('order1'));
    }

    public function testEmbedFailsWhenBaseDocumentIsEmpty(): void
    {
        $baseDocument = new RendererResult();
        $baseDocument->addSuccess('order1', new RenderedDocument());

        $electronicDocument = new RendererResult();
        $electronicDocument->addSuccess('order1', new RenderedDocument(content: $this->getXMLContent()));

        $result = $this->service->embed(
            ['order1' => new DocumentGenerateOperation('order1')],
            Context::createDefaultContext(),
            new DocumentRendererConfig(),
            $baseDocument,
            $this->createElectronicRendererMock($electronicDocument),
            'version'
        );

        static::assertInstanceOf(DocumentException::class, $result->getOrderError('order1'));
    }

    public function testEmbedFailsWhenElectronicDocumentIsMissing(): void
    {
        $baseDocument = new RendererResult();
        $baseDocument->addSuccess('order1', new RenderedDocument(content: $this->getPDFContent()));

        $electronicDocument = new RendererResult();

        $result = $this->service->embed(
            ['order1' => new DocumentGenerateOperation('order1')],
            Context::createDefaultContext(),
            new DocumentRendererConfig(),
            $baseDocument,
            $this->createElectronicRendererMock($electronicDocument),
            'version'
        );

        static::assertInstanceOf(DocumentException::class, $result->getOrderError('order1'));
    }

    public function testEmbedFailsWhenElectronicDocumentIsEmpty(): void
    {
        $baseDocument = new RendererResult();
        $baseDocument->addSuccess('order1', new RenderedDocument(content: $this->getPDFContent()));

        $electronicDocument = new RendererResult();
        $electronicDocument->addSuccess('order1', new RenderedDocument());

        $result = $this->service->embed(
            ['order1' => new DocumentGenerateOperation('order1')],
            Context::createDefaultContext(),
            new DocumentRendererConfig(),
            $baseDocument,
            $this->createElectronicRendererMock($electronicDocument),
            'version'
        );

        static::assertInstanceOf(DocumentException::class, $result->getOrderError('order1'));
    }

    public function testEmbedPropagatesBaseDocumentErrors(): void
    {
        $baseDocument = new RendererResult();
        $baseDocument->addError('order1', DocumentException::generationError('Base document error'));

        $electronicDocument = new RendererResult();
        $electronicDocument->addSuccess('order1', new RenderedDocument(content: $this->getXMLContent()));

        $result = $this->service->embed(
            ['order1' => new DocumentGenerateOperation('order1')],
            Context::createDefaultContext(),
            new DocumentRendererConfig(),
            $baseDocument,
            $this->createElectronicRendererMock($electronicDocument),
            'version'
        );

        static::assertInstanceOf(DocumentException::class, $result->getOrderError('order1'));
    }

    public function testEmbedPropagatesElectronicDocumentErrors(): void
    {
        $baseDocument = new RendererResult();
        $baseDocument->addSuccess('order1', new RenderedDocument(content: $this->getPDFContent()));

        $electronicDocument = new RendererResult();
        $electronicDocument->addError('order1', DocumentException::generationError('Electronic document error'));

        $result = $this->service->embed(
            ['order1' => new DocumentGenerateOperation('order1')],
            Context::createDefaultContext(),
            new DocumentRendererConfig(),
            $baseDocument,
            $this->createElectronicRendererMock($electronicDocument),
            'version'
        );

        static::assertInstanceOf(DocumentException::class, $result->getOrderError('order1'));
    }

    public function testEmbedHandlesMultipleOrders(): void
    {
        $baseDocument = new RendererResult();
        $baseDocument->addSuccess('success', new RenderedDocument(content: $this->getPDFContent()));
        $baseDocument->addSuccess('error', new RenderedDocument());

        $electronicDocument = new RendererResult();
        $electronicDocument->addSuccess('success', new RenderedDocument(content: $this->getXMLContent()));
        $electronicDocument->addSuccess('error', new RenderedDocument());

        $result = $this->service->embed(
            [
                'success' => new DocumentGenerateOperation('success'),
                'error' => new DocumentGenerateOperation('error'),
            ],
            Context::createDefaultContext(),
            new DocumentRendererConfig(),
            $baseDocument,
            $this->createElectronicRendererMock($electronicDocument),
            'version'
        );

        static::assertInstanceOf(RenderedDocument::class, $result->getOrderSuccess('success'));
        static::assertInstanceOf(DocumentException::class, $result->getOrderError('error'));
    }

    private function createElectronicRendererMock(RendererResult $result): AbstractDocumentRenderer
    {
        $mock = $this->createMock(AbstractDocumentRenderer::class);
        $mock->method('render')->willReturn($result);

        return $mock;
    }

    private function getXMLContent(): string
    {
        $content = file_get_contents(__DIR__ . '/../Renderer/_fixtures/invoice_1.xml');

        static::assertIsString($content);

        return $content;
    }

    private function getPDFContent(): string
    {
        $content = file_get_contents(__DIR__ . '/../Renderer/_fixtures/invoice_1.pdf');

        static::assertIsString($content);

        return $content;
    }
}
