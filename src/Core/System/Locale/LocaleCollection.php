<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class LocaleCollection extends EntityCollection
{
    /**
     * @var LocaleStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? LocaleStruct
    {
        return parent::get($id);
    }

    public function current(): LocaleStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return LocaleStruct::class;
    }
}
