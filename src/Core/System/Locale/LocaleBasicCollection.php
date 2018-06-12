<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Locale\LocaleBasicStruct;

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
