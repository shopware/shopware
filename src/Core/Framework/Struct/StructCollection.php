<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

/**
 * @method void        add(Struct $entity)
 * @method void        set(string $key, Struct $entity)
 * @method Struct[]    getIterator()
 * @method Struct[]    getElements()
 * @method Struct|null get(string $key)
 * @method Struct|null first()
 * @method Struct|null last()
 */
class StructCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Struct::class;
    }
}
