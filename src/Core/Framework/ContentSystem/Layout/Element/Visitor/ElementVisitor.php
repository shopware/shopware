<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
interface ElementVisitor
{
    public function enter(ContentElement $element): void;

    public function leave(ContentElement $element): void;
}
