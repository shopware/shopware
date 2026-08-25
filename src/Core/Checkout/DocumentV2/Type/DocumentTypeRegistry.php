<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
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
     * @var array<string, AppDocumentTypeConfig>|null
     */
    private ?array $appTypes = null;

    /**
     * @var array<string, list<string>>|null
     */
    private ?array $formatsByType = null;

    /**
     * @param iterable<AbstractDocumentType> $documentTypes
     */
    public function __construct(
        private readonly iterable $documentTypes,
        private readonly AppFeatureStorage $appFeatureStorage,
    ) {
    }

    public function reset(): void
    {
        $this->coreTypes = null;
        $this->appTypes = null;
        $this->formatsByType = null;
    }

    /**
     * @return array<string, scalar>
     */
    public function getAppConfig(string $documentType): array
    {
        return ($this->appTypes()[$documentType] ?? null)?->getConfig() ?? [];
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
     * @return array<string, AppDocumentTypeConfig>
     */
    private function appTypes(): array
    {
        if ($this->appTypes !== null) {
            return $this->appTypes;
        }

        $appTypes = [];

        foreach ($this->appFeatureStorage->forActiveApps(AppDocumentTypeConfig::class) as $feature) {
            $appTypes[$feature->config->getName()] = $feature->config;
        }

        return $this->appTypes = $appTypes;
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

        foreach ($this->appTypes() as $type => $config) {
            $merged[$type] = $config->getFormats();
        }

        return $this->formatsByType = $merged;
    }
}
