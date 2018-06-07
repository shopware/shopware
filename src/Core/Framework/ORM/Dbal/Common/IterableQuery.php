<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Common;

interface IterableQuery
{
    public function fetch(): array;

    public function fetchCount(): int;
}
