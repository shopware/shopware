<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\UuidGenerator;

interface Generator
{
    public function create(): string;

}