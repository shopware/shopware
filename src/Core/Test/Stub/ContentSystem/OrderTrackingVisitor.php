<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class OrderTrackingVisitor implements ElementVisitor
{
    /**
     * @var list<string>
     */
    public array $log = [];

    public function enter(ContentElement $element): void
    {
        $this->log[] = 'enter:' . $element->getComponent();
    }

    public function leave(ContentElement $element): void
    {
        $this->log[] = 'leave:' . $element->getComponent();
    }
}
