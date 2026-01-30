<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Database;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class TableHelperResetter implements ResetInterface
{
    public function reset(): void
    {
        TableHelper::resetSchemaManager();
    }
}
