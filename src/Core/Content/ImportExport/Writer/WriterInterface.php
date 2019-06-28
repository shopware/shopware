<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

interface WriterInterface
{
    public function append(array $data, int $index): void;

    public function flush(): void;

    public function finish(): void;
}
