<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output\Struct;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Decomposed content structure optimized for deduplication and serialization.
 *
 * @final
 */
#[Package('discovery')]
class DecomposedContentPage extends Struct
{
    /**
     * @param array<ContentElement> $skeletons Element structures without property values
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
        return 'decomposed_content_page';
    }
}
