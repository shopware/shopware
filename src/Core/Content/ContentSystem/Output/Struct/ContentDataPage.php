<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Layout metadata with deduplicated property data and element-to-data mappings.
 *
 * @final
 */
#[Package('discovery')]
class ContentDataPage extends Struct
{
    /**
     * @codeCoverageIgnore
     *
     * @param array<string, mixed> $data Deduplicated property values (refId => value)
     * @param array<string, array<string, string>> $assignments Element-to-property mappings (elementId => [propKey => refId])
     */
    public function __construct(
        public string $layoutId,
        public array $data,
        public array $assignments,
        public string $layoutName,
        public ?string $layoutVersion,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'content_data_page';
    }
}
