<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\ApiRoute;

abstract class AbstractStoreApiTestRoute
{
    abstract public function getDecorated(): AbstractStoreApiTestRoute;
}
