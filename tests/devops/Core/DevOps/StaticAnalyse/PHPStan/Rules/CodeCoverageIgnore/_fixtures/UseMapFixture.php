<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\CodeCoverageIgnore\_fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types as DBALTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid as ShopUuid;

/**
 * @internal
 */
class UseMapFixture
{
    public ?Connection $connection = null;

    public ?DBALTypes $types = null;

    public ?Context $context = null;

    public ?ShopUuid $uuid = null;
}
