<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(LandingPageEntity $entity)
 * @method void                   set(string $key, LandingPageEntity $entity)
 * @method LandingPageEntity[]    getIterator()
 * @method LandingPageEntity[]    getElements()
 * @method LandingPageEntity|null get(string $key)
 * @method LandingPageEntity|null first()
 * @method LandingPageEntity|null last()
 */
class LandingPageCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LandingPageEntity::class;
    }
}
