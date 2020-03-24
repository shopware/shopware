<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

interface ConfigurableInterface
{
    public function setConfig(array $config): void;
}
