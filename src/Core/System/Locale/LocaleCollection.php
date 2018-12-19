<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class LocaleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LocaleEntity::class;
    }
}
