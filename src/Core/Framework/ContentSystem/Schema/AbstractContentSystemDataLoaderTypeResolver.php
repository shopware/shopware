<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemDataLoaderTypeResolver
{
    abstract public function resolve(): ContentSystemDataLoaderTypeMap;
}
