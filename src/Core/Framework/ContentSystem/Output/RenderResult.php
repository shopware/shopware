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
 * The index is nullable because it is optional output, not because it may be missing: a format that serves
 * property values inline needs no index, and skeleton rendering has no property values to index at all.
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
