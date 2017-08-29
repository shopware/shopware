<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

interface PathAware
{
    public function setPath(string $path = ''): void;
}