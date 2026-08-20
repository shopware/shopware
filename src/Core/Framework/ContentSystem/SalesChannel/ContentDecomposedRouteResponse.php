<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel;

use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\Log\Package;

/**
 * The decomposed format's route response. It carries the whole render result, because the response listener
 * needs both halves of it: the rendered forest for the skeletons and the resolved-value index for the data and
 * assignment maps.
 *
 * @final
 */
#[Package('framework')]
class ContentDecomposedRouteResponse extends AbstractContentRouteResponse
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
