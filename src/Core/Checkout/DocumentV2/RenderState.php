<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class RenderState
{
    /**
     * Map of produced dependencies
     * Key: dependency name
     * Value: document content string
     *
     * @var array<string, RenderResult>
     */
    protected array $renderedContent = [];

    public function setRenderedContent(string $format, RenderResult $renderResult): void
    {
        $this->renderedContent[$format] = $renderResult;
    }

    public function getRenderedContent(string $format): ?RenderResult
    {
        return $this->renderedContent[$format] ?? null;
    }
}
