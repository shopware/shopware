<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output;

use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * What one render produced: the finished rendered forest, the layout it came from, and — when the response
 * format asks for it — the {@see ResolvedValueIndex} over that forest's property values.
 *
 * Nothing constructs one in production yet. A later commit wires it into the pipeline's tail, where it
 * replaces the bare forest the response factories are handed today; until then this type only fixes the shape
 * that tail hands on.
 *
 * The index is nullable because it is optional output, not because it may be missing: a format that serves
 * property values inline needs no index, and skeleton rendering has no property values to index at all.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RenderResult
{
    /**
     * @param list<RenderedElement> $tree
     */
    public function __construct(
        public array $tree,
        public LayoutReference $reference,
        public ?ResolvedValueIndex $index,
    ) {
    }
}
