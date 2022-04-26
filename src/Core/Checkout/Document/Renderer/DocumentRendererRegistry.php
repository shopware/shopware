<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;

class DocumentRendererRegistry
{
    /**
     * @var iterable|AbstractDocumentRenderer[]
     */
    protected $documentRenderers;

    public function __construct(iterable $documentRenderers)
    {
        $this->documentRenderers = $documentRenderers;
    }

    public function hasGenerator(string $documentType): bool
    {
        foreach ($this->documentRenderers as $documentRenderer) {
            if ($documentRenderer->supports() !== $documentType) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @throws InvalidDocumentGeneratorTypeException
     */
    public function getRenderer(string $documentType): AbstractDocumentRenderer
    {
        foreach ($this->documentRenderers as $documentRenderer) {
            if ($documentRenderer->supports() !== $documentType) {
                continue;
            }

            return $documentRenderer;
        }

        throw new InvalidDocumentGeneratorTypeException($documentType);
    }
}
