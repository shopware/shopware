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
     * @var array<string, AbstractDocumentType>|null
     */
    private ?array $coreTypes = null;

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

    public function getDocumentType(string $documentType): AbstractDocumentType
    {
        if (!\array_key_exists($documentType, $this->coreTypes())) {
            throw DocumentV2Exception::documentTypeNotFound($documentType);
        }

        return $this->coreTypes()[$documentType];
    }

    /**
     * @return list<string>
     */
    public function getTechnicalNames(): array
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
        $this->coreTypes = null;
        $this->formatsByType = null;
    }

    /**
     * @return array<string, AbstractDocumentType>
     */
    private function coreTypes(): array
    {
        if ($this->coreTypes !== null) {
            return $this->coreTypes;
        }

        $coreTypes = [];

        foreach ($this->documentTypes as $documentType) {
            $coreTypes[$documentType->getTechnicalName()] = $documentType;
        }

        return $this->coreTypes = $coreTypes;
    }

    /**
     * @return array<string, list<string>>
     */
    private function formatsByType(): array
    {
        if ($this->formatsByType !== null) {
            return $this->formatsByType;
        }

        $merged = [];

        foreach ($this->coreTypes() as $technicalName => $documentType) {
            $merged[$technicalName] = array_values(array_unique($documentType->getSupportedFormats()));
        }

        foreach ($this->appDocumentTypeLoader->load() as $type => $formats) {
            // DocumentLifecycleHandler guarantees app identifiers never collide with core
            $merged[$type] = $formats;
        }

        return $this->formatsByType = $merged;
    }
}
