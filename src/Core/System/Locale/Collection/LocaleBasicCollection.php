<?php declare(strict_types=1);

namespace Shopware\System\Locale\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\System\Locale\Struct\LocaleBasicStruct;

class LocaleBasicCollection extends EntityCollection
{
    /**
     * @var LocaleBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? LocaleBasicStruct
    {
        return parent::get($id);
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
