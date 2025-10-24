<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LandingPageContentLayoutEntity>
 */
#[Package('discovery')]
class LandingPageContentLayoutCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LandingPageContentLayoutEntity::class;
    }
}
