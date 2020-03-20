<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

/**
 * @method void        add(Struct $struct)
 * @method void        set(string $key, Struct $struct)
 * @method Struct[]    getIterator()
 * @method Struct[]    getElements()
 * @method Struct|null get(string $key)
 * @method Struct|null first()
 * @method Struct|null last()
 */
class StructCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'dal_struct_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Struct::class;
    }
}
