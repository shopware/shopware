<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('after-sales')]
final class DocumentTypeRegistry implements ResetInterface
{
    /**
     * @var array<string, list<string>>|null
     */
    private ?array $formatsByType = null;

    /**
     * @param iterable<AbstractDocumentType> $documentTypes
     */
    public function __construct(
        private readonly iterable $documentTypes,
        private readonly AppDocumentTypeLoader $appDocumentTypeLoader,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getDocumentTypes(): array
    {
        return array_keys($this->formatsByType());
    }

    /**
     * @return list<string>
     */
    public function getSupportedFormats(string $documentType): array
    {
        return $this->formatsByType()[$documentType] ?? [];
    }

    public function supports(string $documentType): bool
    {
        return isset($this->formatsByType()[$documentType]);
    }

    /**
     * @param list<string> $formats
     *
     * @throws DocumentV2Exception
     */
    public function validateFormats(string $documentType, array $formats): void
    {
        $supported = $this->getSupportedFormats($documentType);

        foreach ($formats as $format) {
            if (!\in_array($format, $supported, true)) {
                throw DocumentV2Exception::unsupportedDocumentFormat($format, $documentType);
            }
        }
    }

    public function reset(): void
    {
        $this->formatsByType = null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function formatsByType(): array
    {
        if ($this->formatsByType !== null) {
            return $this->formatsByType;
        }

        $formatsByType = [];

        foreach ($this->documentTypes as $documentType) {
            $technicalName = $documentType->getTechnicalName();

            foreach ($documentType->getSupportedFormats() as $format) {
                $formatsByType[$technicalName][$format] = true;
            }
        }

        $merged = array_map(
            static fn (array $formats): array => array_keys($formats),
            $formatsByType,
        );

        foreach ($this->appDocumentTypeLoader->load() as $type => $formats) {
            // app document types do not override core types
            $merged[$type] ??= $formats;
        }

        return $this->formatsByType = $merged;
    }
}
