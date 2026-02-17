<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\_helper;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final class EnterTrackingVisitor implements ElementVisitor
{
    /**
     * @var list<string>
     */
    public array $visited = [];

    public function enter(ContentElement $element): void
    {
        $this->visited[] = $element->getComponent();
    }

    public function leave(ContentElement $element): void
    {
    }
}
