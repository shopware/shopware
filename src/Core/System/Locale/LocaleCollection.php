<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class LocaleCollection extends EntityCollection
{
    /**
     * @var LocaleEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? LocaleEntity
    {
        return parent::get($id);
    }

    public function current(): LocaleEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return LocaleEntity::class;
    }
}
