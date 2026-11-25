<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SalesChannel;

use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Holds one rendered element directly in its vars, rather than inside an array. That is the placement the
 * resolver's direct-variable filter answers, and the one a page's `elements` array never produces.
 *
 * @internal
 */
class MockRenderedElementHolderStruct extends Struct
{
    public function __construct(protected RenderedElement $element)
    {
    }
}
