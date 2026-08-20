<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Layout metadata with fully hydrated element trees.
 *
 * @final
 */
#[Package('framework')]
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
     * @codeCoverageIgnore
     */
    public function getApiAlias(): string
    {
        return 'content_page';
    }
}
