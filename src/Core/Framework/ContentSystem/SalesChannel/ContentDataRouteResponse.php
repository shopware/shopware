<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel;

use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\Log\Package;

/**
 * The data format's route response. It carries the whole render result, of which the response listener reads
 * only the resolved-value index: this format serves the values and their assignments without the structure.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentDataRouteResponse extends AbstractContentRouteResponse
{
    private readonly RenderResult $result;

    public function __construct(
        RenderResult $result,
    ) {
        // The parent takes a `Struct`, so the result is handed over as the typed page built from it. Nothing
        // about caching reads it: the HTTP cache key is built from the request (uri, cache hash, cookies) and
        // the cache tags are collected in the route before this response exists.
        parent::__construct(ContentPage::fromRenderResult($result));
        $this->result = $result;
    }

    public function getRenderResult(): RenderResult
    {
        return $this->result;
    }
}
