<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentTypeRegistry
{
    /**
     * @var array<string, list<string>>
     */
    private array $formatsByType;

    /**
     * @param iterable<AbstractDocumentType> $documentTypes
     */
    public function __construct(iterable $documentTypes)
    {
        $formatsByType = [];

        foreach ($documentTypes as $documentType) {
            $technicalName = $documentType->getTechnicalName();

            foreach ($documentType->getSupportedFormats() as $format) {
                $formatsByType[$technicalName][$format] = true;
            }
        }

        $this->formatsByType = array_map(
            static fn (array $formats): array => array_keys($formats),
            $formatsByType,
        );
    }

    /**
     * @return list<string>
     */
    public function getDocumentTypes(): array
    {
        return array_keys($this->formatsByType);
    }

    /**
     * @return list<string>
     */
    public function getSupportedFormats(string $documentType): array
    {
        return $this->formatsByType[$documentType] ?? [];
    }

    public function supports(string $documentType): bool
    {
        return isset($this->formatsByType[$documentType]);
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
}
