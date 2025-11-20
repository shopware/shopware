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
        protected array $skeletons,
        protected array $data,
        protected array $assignments,
        protected string $layoutId,
        protected string $layoutName,
        protected ?string $layoutVersion,
    ) {
    }

    /**
     * @return array<ContentElement>
     */
    public function getSkeletons(): array
    {
        return $this->skeletons;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getAssignments(): array
    {
        return $this->assignments;
    }

    public function getLayoutId(): string
    {
        return $this->layoutId;
    }

    public function getLayoutName(): string
    {
        return $this->layoutName;
    }

    public function getLayoutVersion(): ?string
    {
        return $this->layoutVersion;
    }

    public function getApiAlias(): string
    {
        return 'decomposed_content_page';
    }
}
