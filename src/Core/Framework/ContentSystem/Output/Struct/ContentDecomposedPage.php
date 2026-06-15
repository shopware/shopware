<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Layout metadata with element skeletons, deduplicated property data, and element-to-data mappings.
 *
 * @final
 */
#[Package('framework')]
class ContentDecomposedPage extends Struct
{
    /**
     * @param list<ContentSkeletonElement> $skeletons Element structures without property values
     * @param array<string, mixed> $data Deduplicated property values
     * @param array<string, array<string, string>> $assignments Maps elements to property references
     */
    public function __construct(
        public array $skeletons,
        public array $data,
        public array $assignments,
        public string $layoutId,
        public string $layoutName,
        public ?string $layoutVersion,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'content_decomposed_page';
    }

    /**
     * Extracts data and assignments without skeleton structure.
     */
    public function getContentDataPage(): ContentDataPage
    {
        return new ContentDataPage(
            $this->layoutId,
            $this->data,
            $this->assignments,
            $this->layoutName,
            $this->layoutVersion
        );
    }
}
