<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed.
 */
#[Package('after-sales')]
final class DocumentRendererRegistry
{
    /**
     * @internal
     *
     * @param AbstractDocumentRenderer[] $documentRenderers
     */
    public function __construct(private iterable $documentRenderers)
    {
    }

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function render(string $documentType, array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        foreach ($this->documentRenderers as $documentRenderer) {
            if ($documentRenderer->supports() !== $documentType) {
                continue;
            }

            return $documentRenderer->render($operations, $context, $rendererConfig);
        }

        throw DocumentException::invalidDocumentGeneratorType($documentType);
    }
}
