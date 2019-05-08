<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

interface MapperInterface
{
    public function map(array $data): array;
}
