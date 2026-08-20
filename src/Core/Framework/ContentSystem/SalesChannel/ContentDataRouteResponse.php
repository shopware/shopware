<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel;

use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\Log\Package;

/**
 * The data format's route response. It carries the whole render result, of which the response listener reads
 * only the resolved-value index: this format serves the values and their assignments without the structure.
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
        // The page, not the result, is the struct the response exposes to the framework: it is what the
        // cache-key path reads variables off, exactly as before this response carried the result.
        parent::__construct($result->page);
        $this->result = $result;
    }

    public function getRenderResult(): RenderResult
    {
        return $this->result;
    }
}
