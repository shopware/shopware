<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    abstract public function load(): array;
}
