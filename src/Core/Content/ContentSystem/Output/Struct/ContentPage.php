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
        return 'content_page';
    }

    /**
     * Lazily creates decomposed version with extracted properties.
     */
    public function getDecomposedContentPage(
        DataLoaderConfigSerializerProvider $configSerializerProvider
    ): DecomposedContentPage {
        $skeletons = [];
        $visitor = new PropertiesExtractionVisitor($configSerializerProvider);

        foreach ($this->elements as $element) {
            $skeleton = clone $element;
            $skeleton->traverse($visitor);
            $skeletons[] = $skeleton;
        }

        return new DecomposedContentPage(
            skeletons: $skeletons,
            data: $visitor->getData(),
            assignments: $visitor->getAssignments(),
            layoutId: $this->layoutId,
            layoutName: $this->layoutName,
            layoutVersion: $this->layoutVersion
        );
    }
}
