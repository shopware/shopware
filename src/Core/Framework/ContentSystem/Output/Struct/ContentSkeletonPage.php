<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Layout metadata with the structure of its element trees and none of their property values.
 *
 * This struct reaches the wire through `StructEncoder`, so its property names ARE the response keys — which
 * is why they carry no `layout` prefix: one page vocabulary across the four formats.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSkeletonPage extends Struct
{
    /**
     * @param list<ContentSkeletonElement> $elements
     */
    public function __construct(
        public string $id,
        public array $elements,
        public string $name,
        public ?string $version,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'content_skeleton_page';
    }
}
