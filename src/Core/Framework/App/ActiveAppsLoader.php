<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;

class ActiveAppsLoader
{
    /**
     * @var array|null
     */
    private $activeApps;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getActiveApps(): array
    {
        if ($this->activeApps === null) {
            $this->activeApps = $this->loadApps();
        }

        return $this->activeApps;
    }

    public function resetActiveApps(): void
    {
        $this->activeApps = null;
    }

    private function loadApps(): array
    {
        return $this->connection->executeQuery('
            SELECT `name`, `path`, `author`
            FROM `app`
            WHERE `active` = 1
        ')->fetchAll(FetchMode::ASSOCIATIVE);
    }
}
