<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Log\Package;

/**
 * When a flow action implements this interface, it will be executed within a database transaction.
 */
#[Package('services-settings')]
interface TransactionalAction
{
}
