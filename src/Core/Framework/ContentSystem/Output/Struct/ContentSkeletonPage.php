<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

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
     * @param list<ContentSkeletonElement> $elements
     */
    public function __construct(
        public string $layoutId,
        public array $elements,
        public string $layoutName,
        public ?string $layoutVersion,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'content_skeleton_page';
    }
}
