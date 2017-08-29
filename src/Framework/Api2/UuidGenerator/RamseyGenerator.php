<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\UuidGenerator;

use Ramsey\Uuid\Uuid;

class RamseyGenerator implements Generator
{
    public function create(): string
    {
        return  Uuid::uuid4()->toString();
    }
}