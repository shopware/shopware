<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class DocumentGenerationRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private DataValidator $dataValidator,
        private DocumentFormatValidator $documentFormatValidator,
    ) {
    }

    /**
     * @return \Generator<DocumentGenerationRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== DocumentGenerationRequest::class) {
            return;
        }

        /** @var array{
         *     orderId: string,
         *     orderVersionId: string,
         *     documentType: string,
         *     format?: mixed,
         *     formats?: mixed,
         *     documentComment?: mixed,
         *     documentDate?: mixed,
         *     documentNumber?: mixed
         * } $payload
         */
        $payload = $request->toArray();
        $this->validate($payload);

        $formats = $this->extractFormats($payload);
        $this->documentFormatValidator->validate($payload['documentType'], $formats);

        yield new DocumentGenerationRequest(
            orderId: $payload['orderId'],
            orderVersionId: $payload['orderVersionId'],
            documentType: $payload['documentType'],
            requestedFormats: $formats,
            documentNumber: $this->extractOptionalString($payload, 'documentNumber'),
            documentComment: $this->extractOptionalString($payload, 'documentComment'),
            documentDate: $this->extractOptionalString($payload, 'documentDate'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validate(array $payload): void
    {
        $definition = new DataValidationDefinition();

        $definition
            ->add('orderId', new NotBlank(), new Type('string'), new Uuid())
            ->add('orderVersionId', new NotBlank(), new Type('string'), new Uuid())
            ->add('documentType', new NotBlank(), new Type('string'))
            ->add('documentNumber', new Type('string'))
            ->add('documentComment', new Type('string'))
            ->add('documentDate', new Type('string'));

        if (\array_key_exists('formats', $payload)) {
            $definition->add('formats', new NotBlank(), new Type('array'), new Count(min: 1), new All(new Type('string')));
        } else {
            $definition->add('format', new NotBlank(), new Type('string'));
        }

        $this->dataValidator->validate($payload, $definition);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function extractFormats(array $payload): array
    {
        if (\array_key_exists('formats', $payload) && \is_array($payload['formats'])) {
            /** @var list<string> $formats */
            $formats = array_values($payload['formats']);

            return $formats;
        }

        /** @var string $format */
        $format = $payload['format'];

        return [$format];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOptionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
