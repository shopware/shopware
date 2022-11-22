<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package content
 * @extends EntityCollection<LandingPageTranslationEntity>
 */
class LandingPageTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LandingPageTranslationEntity::class;
    }
}
