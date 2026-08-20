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
 * @final
 */
#[Package('framework')]
class ContentRouteResponse extends AbstractContentRouteResponse
{
    private readonly RenderResult $result;

    public function __construct(
        RenderResult $result,
    ) {
        // The page, not the result, is the struct the response exposes to the framework: it is what the
        // cache-key path reads variables off, exactly as before this response carried the result.
        parent::__construct($result->page);
        $this->result = $result;
    }

    /**
     * TRANSITIONAL, deleted by the model-swap commit together with {@see RenderResult::$page}: an in-process
     * consumer reads the finished forest off the result once the page carries rendered elements itself.
     */
    public function getContentPage(): ContentPage
    {
        return $this->result->page;
    }

    public function getRenderResult(): RenderResult
    {
        return $this->result;
    }
}
