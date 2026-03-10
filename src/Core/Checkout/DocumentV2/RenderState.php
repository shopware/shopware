<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

/**
 * @internal
 */
#[Package('TODO')]
class RenderState
{
    /**
     * Map of produced dependencies
     * Key: dependency name
     * Value: document content string
     *
     * @var array<string, string>
     */
    protected array $renderedContent = [];

    public function setRenderedContent(string $format, string $content): void
    {
        $this->renderedContent[$format] = $content;
    }

    public function getRenderedContent(string $format): string
    {
        // todo: error handling
        return $this->renderedContent[$format];
    }
}
