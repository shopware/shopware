<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Struct;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * ContentPage with properties extracted for deduplication and efficient serialization.
 *
 * @final
 */
#[Package('discovery')]
class DecomposedContentPage extends Struct
{
    /**
     * @param array<string, mixed> $data Extracted property values (deduplicated)
     * @param array<string, array<string, string>> $assignments Element → property → refId mapping
     */
    public function __construct(
        protected ContentElement $skeleton,
        protected array $data,
        protected array $assignments,
        protected string $layoutId,
        protected string $layoutName,
        protected ?string $layoutVersion,
        protected ContentRouteEntity $route
    ) {
    }

    public function getSkeleton(): ContentElement
    {
        return $this->skeleton;
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

    public function getRoute(): ContentRouteEntity
    {
        return $this->route;
    }

    public function getApiAlias(): string
    {
        return 'decomposed_content_page';
    }
}
