<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Encoder;

use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\EncodedContentPage;
use Shopware\Core\Framework\Log\Package;

/**
 * Turns one finished render into the data format's response body: the values a page serves and the map saying
 * which element property points at which of them, with none of the structure around them. It is the half of
 * the decomposed body a client fetches once it already holds a cached skeleton.
 *
 * No structure is built here, not even to discard it. The path this encoder replaces assembled the whole
 * decomposed page — skeleton projection included — and then dropped the skeletons on the way out, which cost
 * every request a projection of a forest nobody would read.
 *
 * The two maps come from {@see ResolvedValueIndexEncoder}, which the decomposed format reads too: the formats
 * are siblings over the same resolved value index rather than one derived from the other, and the guard on the
 * index and the per-leaf protection gate over its values are written once for both.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentDataPageEncoder
{
    /**
     * The wire alias of the data format, an external contract string that outlives the struct this encoder
     * replaces.
     */
    public const PAGE_API_ALIAS = 'content_data_page';

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
            'data' => $index['data'],
            'assignments' => $index['assignments'],
        ], self::PAGE_API_ALIAS);
    }
}
