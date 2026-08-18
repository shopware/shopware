<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.9.0 reason:remove-interface - Will be removed. Register a {@link AbstractDocumentRenderer} instead.
 */
#[Package('after-sales')]
interface FileGeneratorInterface
{
    public function supports(): string;

    public function generate(RenderedDocument $html): string;

    public function getExtension(): string;

    public function getContentType(): string;
}
