<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\AdapterInterface;

interface AdapterFactoryInterface
{
    public function create(array $config): AdapterInterface;

    public function getType(): string;
}
