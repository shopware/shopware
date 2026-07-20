<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * This requirement gates the privilege granting step. When a shopware account is not logged in, privileges are not granted.
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

    public function getGate(): Gate
    {
        return Gate::PRIVILEGES;
    }

    public function isSatisfied(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `user` WHERE `store_token` IS NOT NULL LIMIT 1'
        );
    }

    /**
     * Account-bound services are always-on: they stay active and only their privileges follow the
     * account state.
     */
    public function permitsStateChange(): bool
    {
        return false;
    }
}
