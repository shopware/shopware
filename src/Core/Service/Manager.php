<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Manager
{
    public function __construct(
        private readonly Privileges $privileges,
        private readonly Connection $connection,
    ) {
    }

    public function enable(Context $context): void
    {
        $this->privileges->acceptAllForApps($this->getAllServices(), $context);
    }

    public function disable(Context $context): void
    {
        $this->privileges->revokeAllForApps($this->getAllServices(), $context);
    }

    /**
     * @return list<string>
     */
    private function getAllServices(): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1'
        );
    }
}
