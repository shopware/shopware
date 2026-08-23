<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * The layout's identity triple beside the finished rendered forest, for the consumers that read a page rather
 * than a response body: the Storefront Twig components, and the `Struct` every {@see RenderResult}-carrying
 * route response has to hand its parent.
 *
 * A direct `Struct` subclass and never a `Collection` one: the SEO resolver branches on the `Collection`
 * family, so widening the base class would change what runs over a served page.
 *
 * It is not what any response body is encoded from. The full, decomposed and data encoders read the
 * {@see RenderResult} itself, so the wire shape does not pass through this class.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentPage extends Struct
{
    /**
     * @param list<RenderedElement> $elements
     */
    private function __construct(
        public string $id,
        public array $elements,
        public string $name,
        public ?string $version,
    ) {
    }

    /**
     * The only way to build one: a page is a view onto a finished render, so both halves come from the same
     * result rather than from two arguments a caller could pair wrongly.
     */
    public static function fromRenderResult(RenderResult $result): self
    {
        return new self(
            $result->reference->id,
            $result->tree,
            $result->reference->name,
            $result->reference->version,
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getApiAlias(): string
    {
        return 'content_page';
    }
}
