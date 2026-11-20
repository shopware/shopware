<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Encoder;

use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonElement;
use Shopware\Core\Framework\ContentSystem\Output\Struct\EncodedContentPage;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Turns one finished render into the decomposed format's response body: the structure of the forest with no
 * property values, every value held once, and the map saying which element property points at which value.
 *
 * The two maps are READ, not derived. `ContentPipeline` already builds a {@see ResolvedValueIndex} for this
 * format — the response factory answering `collectsValueIndex()` with true is the only condition on it — and
 * `data()` / `assignments()` are already exactly the two wire maps, one ref to the value it holds and one
 * element id to its property keys to their refs. The path this encoder replaces instead walked a shallow clone
 * of each root emptying properties as it went, and minted its own reference-id grammar while doing so; the
 * index owns that grammar now, so a ref comes from one place rather than two that drift.
 *
 * The maps are encoded by {@see ResolvedValueIndexEncoder}, which the data format reads too: the two formats
 * are siblings over the same index, so the guard on it and the per-leaf protection gate over its values are
 * written once rather than once per format.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentDecomposedPageEncoder
{
    /**
     * The wire aliases of the decomposed format, external contract strings rather than PHP names. The page
     * alias outlives the struct this encoder replaces. The element alias is the one
     * {@see ContentSkeletonElement} reports, repeated here rather than read off it: both formats project the
     * same node shape and a client is entitled to the same alias on it, and this encoder no longer builds that
     * struct to ask.
     */
    public const PAGE_API_ALIAS = 'content_decomposed_page';

    public const ELEMENT_API_ALIAS = 'content_skeleton_element';

    public function __construct(private readonly ResolvedValueIndexEncoder $indexEncoder)
    {
    }

    public function encode(RenderResult $result): EncodedContentPage
    {
        $index = $this->indexEncoder->encode($result);

        return new EncodedContentPage([
            'id' => $result->reference->id,
            'name' => $result->reference->name,
            'version' => $result->reference->version,
            'skeletons' => array_map($this->encodeSkeleton(...), $result->tree),
            'data' => $index['data'],
            'assignments' => $index['assignments'],
        ], self::PAGE_API_ALIAS);
    }

    /**
     * `id`, `component` and `slots` are always present — an empty `slots` map serializes as `[]`, never `{}` —
     * `style` is omitted when empty, and the `apiAlias` goes last on every node at every depth — the full
     * format's element conventions, minus the properties this format serves out of `data` instead.
     *
     * @return array<string, mixed>
     */
    private function encodeSkeleton(RenderedElement $element): array
    {
        $data = [
            'id' => $element->id,
            'component' => $element->component,
        ];

        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = array_map($this->encodeSkeleton(...), $children);
        }

        $data['slots'] = $slots;

        if (!$element->style->isEmpty()) {
            $data['style'] = $element->style->toArray();
        }

        $data['apiAlias'] = self::ELEMENT_API_ALIAS;

        return $data;
    }
}
