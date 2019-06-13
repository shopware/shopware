<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

interface WriterInterface
{
    public function append(array $data): void;

    public function flush(): void;
}
