<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;

abstract class AbstractDocumentRenderer
{
    abstract public function supports(): string;

    /**
     * @param DocumentGenerateOperation[] $operations
     *
     * @return RenderedDocument[]
     */
    abstract public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): array;

    abstract public function getDecorated(): AbstractDocumentRenderer;
}
