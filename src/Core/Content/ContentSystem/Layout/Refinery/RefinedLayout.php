<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Refinery;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class RefinedLayout
{
    /**
     * @internal
     */
    public function __construct(
        public readonly ContentLayoutEntity $layoutEntity,
        public readonly ContentElement $rootElement,
    ) {
    }
}
