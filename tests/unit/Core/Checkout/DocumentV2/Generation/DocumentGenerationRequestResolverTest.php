<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequestResolver;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentGenerationRequestResolver::class)]
class DocumentGenerationRequestResolverTest extends TestCase
{
    public function testResolveBuildsDocumentGenerationRequest(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'orderVersionId' => '018f5972f9ea72a0be49f7c39f72a2a1',
            'documentType' => DocumentType::INVOICE->value,
            'formats' => [
                DocumentFormat::PDF->value,
                DocumentFormat::HTML->value,
            ],
            'documentNumber' => ' 1000 ',
            'documentComment' => ' Comment ',
            'documentDate' => '2026-07-13T00:00:00.000+00:00',
        ]);

        $result = $this->resolveRequest($request);

        static::assertSame('018f5972f9ea72a0be49f7c39f72a2a0', $result->orderId);
        static::assertSame('018f5972f9ea72a0be49f7c39f72a2a1', $result->orderVersionId);
        static::assertSame(DocumentType::INVOICE->value, $result->documentType);
        static::assertSame([DocumentFormat::PDF->value, DocumentFormat::HTML->value], $result->requestedFormats);
        static::assertSame('1000', $result->documentNumber);
        static::assertSame('Comment', $result->documentComment);
        static::assertSame('2026-07-13T00:00:00.000+00:00', $result->documentDate);
    }

    public function testResolveBuildsDocumentGenerationRequestWithoutOrderVersionId(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'documentType' => DocumentType::INVOICE->value,
            'formats' => [
                DocumentFormat::PDF->value,
            ],
        ]);

        $result = $this->resolveRequest($request);

        static::assertNull($result->orderVersionId);
    }

    public function testResolveBuildsDocumentGenerationRequestFromPreviewPayload(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'orderVersionId' => '018f5972f9ea72a0be49f7c39f72a2a1',
            'documentType' => DocumentType::INVOICE->value,
            'format' => DocumentFormat::HTML->value,
            'documentNumber' => '1000',
        ]);

        $result = $this->resolveRequest($request);

        static::assertSame('018f5972f9ea72a0be49f7c39f72a2a0', $result->orderId);
        static::assertSame('018f5972f9ea72a0be49f7c39f72a2a1', $result->orderVersionId);
        static::assertSame(DocumentType::INVOICE->value, $result->documentType);
        static::assertSame([DocumentFormat::HTML->value], $result->requestedFormats);
        static::assertSame('1000', $result->documentNumber);
        static::assertNull($result->documentComment);
    }

    public function testResolveRejectsUnsupportedFormats(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'orderVersionId' => '018f5972f9ea72a0be49f7c39f72a2a1',
            'documentType' => DocumentType::INVOICE->value,
            'formats' => [
                DocumentFormat::HTML->value,
                DocumentFormat::PDF->value,
            ],
        ]);

        static::expectExceptionObject(
            DocumentV2Exception::unsupportedDocumentFormat(
                DocumentFormat::PDF->value,
                DocumentType::INVOICE->value,
            )
        );

        $this->resolveRequest(
            $request,
            new DocumentRendererRegistry([
                new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
            ]),
        );
    }

    public function testResolveRejectsUnsupportedFormat(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'orderVersionId' => '018f5972f9ea72a0be49f7c39f72a2a1',
            'documentType' => DocumentType::INVOICE->value,
            'format' => DocumentFormat::PDF->value,
        ]);

        static::expectExceptionObject(
            DocumentV2Exception::unsupportedDocumentFormat(
                DocumentFormat::PDF->value,
                DocumentType::INVOICE->value,
            )
        );

        $this->resolveRequest(
            $request,
            new DocumentRendererRegistry([
                new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
            ]),
        );
    }

    public function testResolveIgnoresOtherArgumentTypes(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
        ]);

        $resolver = $this->createResolver();

        $result = iterator_to_array(
            $resolver->resolve($request, new ArgumentMetadata('request', Request::class, false, false, null))
        );

        static::assertSame([], $result);
    }

    private function resolveRequest(
        Request $request,
        ?DocumentRendererRegistry $documentRendererRegistry = null,
    ): DocumentGenerationRequest {
        $result = iterator_to_array(
            $this->createResolver($documentRendererRegistry)->resolve(
                $request,
                new ArgumentMetadata('generationRequest', DocumentGenerationRequest::class, false, false, null)
            )
        );

        static::assertCount(1, $result);

        return $result[0];
    }

    private function createResolver(
        ?DocumentRendererRegistry $documentRendererRegistry = null,
    ): DocumentGenerationRequestResolver {
        return new DocumentGenerationRequestResolver(
            new DataValidator(Validation::createValidatorBuilder()->getValidator()),
            $documentRendererRegistry ?? new DocumentRendererRegistry([
                new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
                new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
            ]),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createRequest(array $payload): Request
    {
        $request = new Request(
            content: (string) json_encode($payload, \JSON_THROW_ON_ERROR),
        );
        $request->headers->set('CONTENT_TYPE', 'application/json');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext());

        return $request;
    }
}
