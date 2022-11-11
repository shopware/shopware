<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

/**
 * @package core
 * @template TElement of Struct
 *
 * @extends Collection<TElement>
 */
class StructCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'dal_struct_collection';
    }

    /**
     * @return class-string<Struct>
     */
    protected function getExpectedClass(): ?string
    {
        return Struct::class;
    }
}
