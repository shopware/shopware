<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package content
 * @extends EntityCollection<LandingPageEntity>
 */
class LandingPageCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LandingPageEntity::class;
    }
}
