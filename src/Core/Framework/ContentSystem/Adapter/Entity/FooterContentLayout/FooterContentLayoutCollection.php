<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Entity\FooterContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<FooterContentLayoutEntity>
 */
#[Package('framework')]
class FooterContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'footer_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return FooterContentLayoutEntity::class;
    }
}
