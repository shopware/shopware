<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use Shopware\Core\Content\ImportExport\Struct\Config;

/**
 * @experimental We might break this in v6.2
 */
abstract class AbstractWriter
{
    abstract public function append(Config $config, array $data, int $index): void;

    abstract public function flush(Config $config, string $targetPath): void;

    abstract public function finish(Config $config, string $targetPath): void;
}
