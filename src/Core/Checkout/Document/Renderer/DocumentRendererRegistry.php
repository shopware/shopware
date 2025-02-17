<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
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

    /**
     * @deprecated tag:v6.7.0 - will be removed without replacement
     */
    public function finalize(string $documentType, DocumentGenerateOperation $operation, Context $context, DocumentRendererConfig $rendererConfig, RendererResult $result): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Method will be removed without replacement');

        foreach ($this->documentRenderers as $documentRenderer) {
            if ($documentRenderer->supports() !== $documentType) {
                continue;
            }

            $documentRenderer->finalize($operation, $context, $rendererConfig, $result);

            return;
        }

        throw DocumentException::invalidDocumentGeneratorType($documentType);
    }
}
