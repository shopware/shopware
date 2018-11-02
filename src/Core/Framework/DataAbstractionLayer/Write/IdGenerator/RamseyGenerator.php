<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\IdGenerator;

use Shopware\Core\Framework\Struct\Uuid;

class RamseyGenerator implements Generator
{
    public function create(): string
    {
        return  Uuid::uuid4()->getHex();
    }

    public function toStorageValue(string $value): string
    {
        return Uuid::fromStringToBytes($value);
    }
}
