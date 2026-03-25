<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output\Struct;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Layout metadata with element trees before hydration.
 *
 * @final
 */
#[Package('discovery')]
class ContentSkeletonPage extends Struct
{
    /**
     * @param iterable<ContentElement> $elements
     */
    public function __construct(
        public string $layoutId,
        public iterable $elements,
        public string $layoutName,
        public ?string $layoutVersion,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'content_skeleton_page';
    }
}
