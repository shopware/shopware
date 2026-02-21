<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output\Struct;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\PropertiesExtractionVisitor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Layout metadata with fully hydrated element trees.
 *
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

    /**
     * Lazily creates decomposed version with extracted properties.
     */
    public function getContentDecomposedPage(
        DataLoaderConfigSerializerProvider $configSerializerProvider
    ): ContentDecomposedPage {
        $visitor = new PropertiesExtractionVisitor($configSerializerProvider);

        foreach ($this->elements as $element) {
            $clone = clone $element;
            $clone->traverse($visitor);
        }

        return new ContentDecomposedPage(
            ContentSkeletonElement::fromElements($this->elements),
            $visitor->getData(),
            $visitor->getAssignments(),
            $this->layoutId,
            $this->layoutName,
            $this->layoutVersion
        );
    }

    /**
     * Creates skeleton version without hydrated data.
     */
    public function getContentSkeletonPage(): ContentSkeletonPage
    {
        return new ContentSkeletonPage(
            $this->layoutId,
            ContentSkeletonElement::fromElements($this->elements),
            $this->layoutName,
            $this->layoutVersion
        );
    }

    /**
     * Creates data version with hydrated data and assignments to the skeleton but without the skeleton.
     */
    public function getContentDataPage(
        DataLoaderConfigSerializerProvider $configSerializerProvider
    ): ContentDataPage {
        return $this->getContentDecomposedPage($configSerializerProvider)->getContentDataPage();
    }

    /**
     * @codeCoverageIgnore
     */
    public function getApiAlias(): string
    {
        return 'content_page';
    }
}
