<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequestResolver;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentType;
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
            'documentType' => DocumentType::INVOICE->value,
            'formats' => [
                DocumentFormat::PDF->value,
                DocumentFormat::HTML->value,
            ],
            'documentNumber' => ' 1000 ',
            'documentComment' => ' Comment ',
            'documentDate' => '2026-07-13T00:00:00.000+00:00',
            'deliveryDate' => '2026-07-15T00:00:00.000+00:00',
            'referencedDocumentId' => '018f5972f9ea72a0be49f7c39f72a2a2',
        ]);

        $result = $this->resolveRequest($request);

        static::assertSame('018f5972f9ea72a0be49f7c39f72a2a0', $result->orderId);
        static::assertSame(DocumentType::INVOICE->value, $result->documentType);
        static::assertSame([DocumentFormat::PDF->value, DocumentFormat::HTML->value], $result->requestedFormats);
        static::assertSame('1000', $result->documentNumber);
        static::assertSame('Comment', $result->documentComment);
        static::assertSame('2026-07-13T00:00:00.000+00:00', $result->documentDate);
        static::assertSame('2026-07-15T00:00:00.000+00:00', $result->deliveryDate);
        static::assertSame('018f5972f9ea72a0be49f7c39f72a2a2', $result->referencedDocumentId);
    }

    public function testResolveBuildsDocumentGenerationRequestFromPreviewPayload(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'documentType' => DocumentType::INVOICE->value,
            'format' => DocumentFormat::HTML->value,
            'documentNumber' => '1000',
        ]);

        $result = $this->resolveRequest($request);

        static::assertSame('018f5972f9ea72a0be49f7c39f72a2a0', $result->orderId);
        static::assertSame(DocumentType::INVOICE->value, $result->documentType);
        static::assertSame([DocumentFormat::HTML->value], $result->requestedFormats);
        static::assertSame('1000', $result->documentNumber);
        static::assertNull($result->documentComment);
        static::assertNull($result->deliveryDate);
        static::assertNull($result->referencedDocumentId);
    }

    #[DataProvider('malformedDateProvider')]
    public function testResolveRejectsAValueThatDoesNotParseAsADateTime(string $field): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'documentType' => DocumentType::INVOICE->value,
            'format' => DocumentFormat::HTML->value,
            $field => 'foo',
        ]);

        $this->expectException(ConstraintViolationException::class);

        $this->resolveRequest($request);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function malformedDateProvider(): \Generator
    {
        yield 'documentDate must parse as a date-time' => ['documentDate'];
        yield 'deliveryDate must parse as a date-time' => ['deliveryDate'];
    }

    #[DataProvider('parseableDateProvider')]
    public function testResolveAcceptsAnyParseableDateTimeVariant(string $value): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'documentType' => DocumentType::INVOICE->value,
            'format' => DocumentFormat::HTML->value,
            'documentDate' => $value,
            'deliveryDate' => $value,
        ]);

        $result = $this->resolveRequest($request);

        static::assertSame($value, $result->documentDate);
        static::assertSame($value, $result->deliveryDate);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function parseableDateProvider(): \Generator
    {
        yield 'ISO 8601 with milliseconds and offset' => ['2026-07-13T00:00:00.000+00:00'];
        yield 'ISO 8601 without milliseconds' => ['2026-07-13T00:00:00+00:00'];
        yield 'date without time' => ['2026-07-13'];
    }

    public function testResolveRejectsAMalformedReferencedDocumentId(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
            'documentType' => DocumentType::INVOICE->value,
            'format' => DocumentFormat::HTML->value,
            'referencedDocumentId' => 'not-a-uuid',
        ]);

        $this->expectException(ConstraintViolationException::class);

        $this->resolveRequest($request);
    }

    public function testResolveRejectsUnsupportedFormats(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
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
            new DocumentTypeRegistry([
                new StaticDocumentType(DocumentType::INVOICE->value, [DocumentFormat::HTML->value]),
            ]),
        );
    }

    public function testResolveRejectsUnsupportedFormat(): void
    {
        $request = $this->createRequest([
            'orderId' => '018f5972f9ea72a0be49f7c39f72a2a0',
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
            new DocumentTypeRegistry([
                new StaticDocumentType(DocumentType::INVOICE->value, [DocumentFormat::HTML->value]),
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
        ?DocumentTypeRegistry $documentTypeRegistry = null,
    ): DocumentGenerationRequest {
        $result = iterator_to_array(
            $this->createResolver($documentTypeRegistry)->resolve(
                $request,
                new ArgumentMetadata('generationRequest', DocumentGenerationRequest::class, false, false, null)
            )
        );

        static::assertCount(1, $result);

        return $result[0];
    }

    private function createResolver(
        ?DocumentTypeRegistry $documentTypeRegistry = null,
    ): DocumentGenerationRequestResolver {
        return new DocumentGenerationRequestResolver(
            new DataValidator(Validation::createValidatorBuilder()->getValidator()),
            $documentTypeRegistry ?? new DocumentTypeRegistry([
                new StaticDocumentType(DocumentType::INVOICE->value, [
                    DocumentFormat::HTML->value,
                    DocumentFormat::PDF->value,
                ]),
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
