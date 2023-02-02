<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\System\SalesChannel\BaseContext;

/**
 * @internal
 */
abstract class AbstractBaseContextFactory
{
    abstract public function getDecorated(): AbstractBaseContextFactory;

    abstract public function create(string $salesChannelId, array $options = []): BaseContext;
}
