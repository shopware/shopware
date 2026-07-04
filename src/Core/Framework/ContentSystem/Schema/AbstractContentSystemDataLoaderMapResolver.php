<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemDataLoaderMapResolver
{
    abstract public function resolve(): ContentSystemDataLoaderMap;
}
