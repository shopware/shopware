<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output\Struct;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\PropertiesExtractionVisitor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('discovery')]
class ContentPage extends Struct
{
    public function __construct(
        protected string $layoutId,
        protected ContentElement $layout,
        protected string $layoutName,
        protected ?string $layoutVersion,
    ) {
    }

    public function getLayoutId(): string
    {
        return $this->layoutId;
    }

    public function getLayout(): ContentElement
    {
        return $this->layout;
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
        return 'content_page';
    }

    /**
     * Lazily creates decomposed version with extracted properties.
     */
    public function getDecomposedContentPage(
        DataLoaderConfigSerializerProvider $configSerializerProvider
    ): DecomposedContentPage {
        $skeleton = clone $this->layout;

        $visitor = new PropertiesExtractionVisitor($configSerializerProvider);
        $skeleton->traverse($visitor);

        return new DecomposedContentPage(
            skeleton: $skeleton,
            data: $visitor->getData(),
            assignments: $visitor->getAssignments(),
            layoutId: $this->layoutId,
            layoutName: $this->layoutName,
            layoutVersion: $this->layoutVersion
        );
    }
}
