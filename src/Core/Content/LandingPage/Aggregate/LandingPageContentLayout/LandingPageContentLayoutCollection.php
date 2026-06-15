<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<LandingPageContentLayoutEntity>
 */
#[Package('discovery')]
class LandingPageContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'landing_page_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return LandingPageContentLayoutEntity::class;
    }
}
