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
     * @var array<string, AbstractDocumentType>
     */
    private array $typesByName;

    /**
     * @param iterable<AbstractDocumentType> $documentTypes
     */
    public function __construct(iterable $documentTypes)
    {
        $formatsByType = [];
        $typesByName = [];

        foreach ($documentTypes as $documentType) {
            $technicalName = $documentType->getTechnicalName();
            $typesByName[$technicalName] = $documentType;

            foreach ($documentType->getSupportedFormats() as $format) {
                $formatsByType[$technicalName][$format] = true;
            }
        }

        $this->typesByName = $typesByName;
        $this->formatsByType = array_map(
            static fn (array $formats): array => array_keys($formats),
            $formatsByType,
        );
    }

    public function getDocumentType(string $documentType): AbstractDocumentType
    {
        if (!\array_key_exists($documentType, $this->typesByName)) {
            throw DocumentV2Exception::documentTypeNotFound($documentType);
        }

        return $this->typesByName[$documentType];
    }

    /**
     * @return list<string>
     */
    public function getTechnicalNames(): array
    {
        return array_keys($this->typesByName);
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
