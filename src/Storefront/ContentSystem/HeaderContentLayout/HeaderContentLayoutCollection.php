<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\HeaderContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<HeaderContentLayoutEntity>
 */
#[Package('framework')]
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
