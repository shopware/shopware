<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Slot;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @final
 *
 * @extends Collection<ContentElement>
 */
#[Package('framework')]
class SlotContent extends Collection
{
    public function getApiAlias(): string
    {
        return 'content_element_slot_content';
    }

    protected function getExpectedClass(): string
    {
        return ContentElement::class;
    }
}
