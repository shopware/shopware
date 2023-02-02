<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LocaleEntity>
 */
class LocaleCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'locale_collection';
    }

    protected function getExpectedClass(): string
    {
        return LocaleEntity::class;
    }
}
