<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Use {@link AbstractDocumentRenderer} instead.
 */
#[Package('after-sales')]
abstract class AbstractDocumentTypeRenderer
{
    abstract public function getContentType(): string;

    abstract public function render(RenderedDocument $document): string;

    abstract public function getDecorated(): AbstractDocumentTypeRenderer;
}
