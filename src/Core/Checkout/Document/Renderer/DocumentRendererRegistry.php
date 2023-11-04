<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
final class DocumentRendererRegistry
{
    /**
     * @internal
     *
     * @param AbstractDocumentRenderer[] $documentRenderers
     */
    public function __construct(protected iterable $documentRenderers)
    {
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
