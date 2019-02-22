<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(LocaleEntity $entity)
 * @method void              set(string $key, LocaleEntity $entity)
 * @method LocaleEntity[]    getIterator()
 * @method LocaleEntity[]    getElements()
 * @method LocaleEntity|null get(string $key)
 * @method LocaleEntity|null first()
 * @method LocaleEntity|null last()
 */
class LocaleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LocaleEntity::class;
    }
}
