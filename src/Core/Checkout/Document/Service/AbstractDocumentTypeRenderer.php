<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
abstract class AbstractDocumentTypeRenderer
{
    abstract public function getContentType(): string;

    abstract public function render(RenderedDocument $document): string;

    abstract public function getDecorated(): AbstractDocumentTypeRenderer;
}
