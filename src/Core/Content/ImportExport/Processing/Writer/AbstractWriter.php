<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('services-settings')]
abstract class AbstractWriter
{
    /**
     * @param array<string, mixed> $data
     */
    abstract public function append(Config $config, array $data, int $index): void;

    abstract public function flush(Config $config, string $targetPath): void;

    abstract public function finish(Config $config, string $targetPath): void;

    protected function getDecorated(): AbstractWriter
    {
        throw new DecorationPatternException(self::class);
    }
}
