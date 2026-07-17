<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentRendererRegistry
{
    /**
     * @var array<string, array<string, AbstractDocumentRenderer>>
     */
    private array $renderersByDocumentType;

    /**
     * @var array<string, string>
     */
    private array $fileExtensionsByFormat;

    /**
     * @param iterable<AbstractDocumentRenderer> $documentRenderers
     */
    public function __construct(iterable $documentRenderers)
    {
        $renderersByDocumentType = [];
        $fileExtensionsByFormat = [];

        foreach ($documentRenderers as $renderer) {
            $format = $renderer->getFormat();
            $fileExtensionsByFormat[$format] = $renderer->getFileExtension();

            foreach ($renderer->getDocumentTypes() as $documentType) {
                if (isset($renderersByDocumentType[$documentType][$format])) {
                    throw DocumentV2Exception::duplicateRenderer($format, $documentType);
                }

                $renderersByDocumentType[$documentType][$format] = $renderer;
            }
        }

        $this->renderersByDocumentType = $renderersByDocumentType;
        $this->fileExtensionsByFormat = $fileExtensionsByFormat;
    }

    /**
     * Returns the renderer used for one exact format and document type combination.
     *
     * @throws DocumentV2Exception
     */
    public function getRenderer(string $format, string $documentType): AbstractDocumentRenderer
    {
        $renderers = $this->mapRenderersByFormat($documentType);

        if (!isset($renderers[$format])) {
            throw DocumentV2Exception::rendererNotFound($format, $documentType);
        }

        return $renderers[$format];
    }

    /**
     * Builds a format => renderer map for all renderers that support the given document type.
     *
     * @return array<string, AbstractDocumentRenderer>
     */
    public function mapRenderersByFormat(string $documentType): array
    {
        return $this->renderersByDocumentType[$documentType] ?? [];
    }

    /**
     * Returns a map of document types to the list of formats they support.
     * eg. ['invoice' => ['pdf', 'html'], 'credit_note' => ['pdf']]
     *
     * @return array<string, list<string>>
     */
    public function getSupportedFormatsByDocumentType(): array
    {
        return array_map(function ($renderers) {
            return array_keys($renderers);
        }, $this->renderersByDocumentType);
    }

    public function getFileExtension(string $format): ?string
    {
        return $this->fileExtensionsByFormat[$format] ?? null;
    }
}
