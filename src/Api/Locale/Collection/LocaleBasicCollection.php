<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Locale\Struct\LocaleBasicStruct;

class LocaleBasicCollection extends EntityCollection
{
    /**
     * @var LocaleBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? LocaleBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): LocaleBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return LocaleBasicStruct::class;
    }
}
