<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
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
        try {
            return $this->connection->executeQuery('
                SELECT `name`, `path`, `author`
                FROM `app`
                WHERE `active` = 1
            ')->fetchAll(FetchMode::ASSOCIATIVE);
        } catch (\Throwable $e) {
            if (\defined('\STDERR')) {
                fwrite(\STDERR, 'Warning: Failed to load apps. Message: ' . $e->getMessage() . \PHP_EOL);
            }
        }

        return [];
    }
}
