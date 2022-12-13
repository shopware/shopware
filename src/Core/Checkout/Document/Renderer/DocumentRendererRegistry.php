<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Framework\Context;

/**
 * @package customer-order
 */
final class DocumentRendererRegistry
{
    /**
     * @var iterable|AbstractDocumentRenderer[]
     */
    protected $documentRenderers;

    /**
     * @internal
     */
    public function __construct(iterable $documentRenderers)
    {
        $this->documentRenderers = $documentRenderers;
    }

    public function render(string $documentType, array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        foreach ($this->documentRenderers as $documentRenderer) {
            if ($documentRenderer->supports() !== $documentType) {
                continue;
            }

            return $documentRenderer->render($operations, $context, $rendererConfig);
        }

        throw new InvalidDocumentGeneratorTypeException($documentType);
    }
}
