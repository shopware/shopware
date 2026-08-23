<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel;

use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\Log\Package;

/**
 * The full format's route response. It carries the whole render result, because its two audiences read
 * different parts of it: the response listener encodes the rendered forest, while an in-process consumer
 * takes the typed page.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentRouteResponse extends AbstractContentRouteResponse
{
    private readonly RenderResult $result;

    private readonly ContentPage $page;

    public function __construct(
        RenderResult $result,
    ) {
        $this->result = $result;
        $this->page = ContentPage::fromRenderResult($result);

        // The parent takes a `Struct` and this is the only one the response has: nothing about caching reads
        // it. The HTTP cache key is built from the request (uri, cache hash, cookies) and the cache tags are
        // collected in the route before this response exists.
        parent::__construct($this->page);
    }

    /**
     * The typed page an in-process consumer reads, built from the same render result the response carries.
     * The response BODY is not encoded from it — `ContentResponseEncodingListener` encodes the result.
     */
    public function getContentPage(): ContentPage
    {
        return $this->page;
    }

    public function getRenderResult(): RenderResult
    {
        return $this->result;
    }
}
