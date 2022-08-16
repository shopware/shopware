<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

/**
 * @extends Collection<Struct>
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
