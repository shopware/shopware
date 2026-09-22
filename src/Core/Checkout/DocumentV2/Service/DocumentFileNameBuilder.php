<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service;

use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Builds a human readable file name for a download that bundles one or more documents, e.g. a merged PDF or a ZIP
 * archive.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentFileNameBuilder
{
    /**
     * Fallback name if the documents of a download do not share a single document type
     */
    private const MIXED_DOCUMENT_TYPES_FILE_NAME = 'documents';

    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /**
     * Builds the file name without the file extension, e.g. `invoice_10000` for a single document or
     * `delivery_note_2026-09-10` for multiple documents of the same document type.
     */
    public function build(DocumentCollection $documents): string
    {
        if ($documents->count() === 1) {
            $document = $documents->first();
            $documentName = $document !== null ? $this->getDocumentName($document) : null;

            if ($documentName !== null) {
                return $documentName;
            }
        }

        return $this->getSharedFileNamePrefix($documents) . $this->clock->now()->format('Y-m-d');
    }

    /**
     * Resolves the name the document was rendered with, so that downloading a single document always yields the
     * same file name, no matter whether it is downloaded on its own or bundled with other documents.
     */
    private function getDocumentName(DocumentEntity $document): ?string
    {
        $config = $document->getConfig();

        $documentNumber = $this->getConfigValue($config, 'documentNumber');
        if ($documentNumber === '') {
            $documentNumber = $document->getDocumentNumber() ?? '';
        }

        if ($documentNumber === '') {
            return null;
        }

        $prefix = $this->getConfigValue($config, 'filenamePrefix');
        $suffix = $this->getConfigValue($config, 'filenameSuffix');

        if ($prefix === '' && $suffix === '') {
            // Without a configured prefix the document number alone would not describe the content of the file
            $typeName = $document->getTypeName();
            $prefix = $typeName !== null ? $typeName . '_' : '';
        }

        return $prefix . $documentNumber . $suffix;
    }

    /**
     * Documents are usually downloaded grouped by document type, so the file name prefix that is configured for that
     * document type describes the content of the download best.
     */
    private function getSharedFileNamePrefix(DocumentCollection $documents): string
    {
        $typeNames = [];
        $prefixes = [];

        foreach ($documents as $document) {
            $typeName = $document->getTypeName() ?? '';
            if ($typeName !== '') {
                $typeNames[] = $typeName;
            }

            $prefix = $this->getConfigValue($document->getConfig(), 'filenamePrefix');
            if ($prefix !== '') {
                $prefixes[] = $prefix;
            }
        }

        $typeNames = array_values(array_unique($typeNames));
        $prefixes = array_values(array_unique($prefixes));

        if (\count($typeNames) !== 1) {
            return self::MIXED_DOCUMENT_TYPES_FILE_NAME . '_';
        }

        if (\count($prefixes) === 1) {
            return $prefixes[0];
        }

        return $typeNames[0] . '_';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function getConfigValue(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }
}
