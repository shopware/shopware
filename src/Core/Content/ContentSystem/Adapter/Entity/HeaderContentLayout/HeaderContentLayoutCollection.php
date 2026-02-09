<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<HeaderContentLayoutEntity>
 */
#[Package('discovery')]
class HeaderContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'header_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return HeaderContentLayoutEntity::class;
    }
}
