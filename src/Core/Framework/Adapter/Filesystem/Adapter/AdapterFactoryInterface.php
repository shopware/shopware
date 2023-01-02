<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use Shopware\Core\Framework\Log\Package;
use League\Flysystem\AdapterInterface;

/**
 * @package core
 */
#[Package('core')]
interface AdapterFactoryInterface
{
    public function create(array $config): AdapterInterface;

    public function getType(): string;
}
