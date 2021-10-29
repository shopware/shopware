<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ActiveAppsLoader
{
    private ?array $activeApps = null;

    private Connection $connection;

    private AbstractAppLoader $appLoader;

    public function __construct(Connection $connection, AbstractAppLoader $appLoader)
    {
        $this->connection = $connection;
        $this->appLoader = $appLoader;
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
            return $this->connection->fetchAllAssociative('
                SELECT `name`, `path`, `author`
                FROM `app`
                WHERE `active` = 1
            ');
        } catch (\Throwable $e) {
            if (\defined('\STDERR')) {
                fwrite(\STDERR, 'Warning: Failed to load apps. Loading apps from local. Message: ' . $e->getMessage() . \PHP_EOL);
            }

            return array_map(function (Manifest $manifest) {
                return [
                    'name' => $manifest->getMetadata()->getName(),
                    'path' => $manifest->getPath(),
                    'author' => $manifest->getMetadata()->getAuthor(),
                ];
            }, $this->appLoader->load());
        }
    }
}
