<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ShopwareAccountRequirement implements ServiceRequirement
{
    public const NAME = 'shopware_account';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function isSatisfied(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `user` WHERE `store_token` IS NOT NULL LIMIT 1'
        );
    }
}
