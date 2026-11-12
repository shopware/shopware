<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Format;

use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractResponseFactory
{
    abstract public function getRenderingMode(): RenderingMode;

    /**
     * Whether this format rebuilds its body from the resolved-value index rather than serving property values
     * inline. Together with {@see getRenderingMode()} these are the two answers the route hands the pipeline:
     * the rendering mode alone does not carry the index signal, because decomposed and data render in FULL
     * mode exactly like the full format and differ only in how the body is assembled.
     */
    abstract public function collectsValueIndex(): bool;

    abstract public function createResponse(RenderResult $result): AbstractContentRouteResponse;
}
