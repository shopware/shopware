<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

class StructCollection extends Collection
{
    public function get($key): ?Struct
    {
        return $this->elements[$key] ?? null;
    }

    protected function getExpectedClass(): ?string
    {
        return Struct::class;
    }
}
