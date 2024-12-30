<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractDocumentTypeRenderer
{
    /**
     * @var array<string, string>
     */
    public array $htmlRenderer = [];

    abstract public function getContentType(): string;

    abstract public function render(RenderedDocument $document): string;

    /**
     * @param array<mixed> $templateOptions
     */
    abstract public function templateRenderer(array $templateOptions, string $html = ''): void;

    abstract public function getDecorated(): AbstractDocumentTypeRenderer;
}
