<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 * @phpstan-type App array{name: string, path: string, author: string|null}
 */
class ActiveAppsLoader implements ResetInterface
{
    /**
     * @var App[]|null
     */
    private ?array $activeApps = null;

    private Connection $connection;

    private AbstractAppLoader $appLoader;

    public function __construct(Connection $connection, AbstractAppLoader $appLoader)
    {
        $this->connection = $connection;
        $this->appLoader = $appLoader;
    }

    /**
     * @return App[]
     */
    public function getActiveApps(): array
    {
        if (EnvironmentHelper::getVariable('DISABLE_EXTENSIONS', false)) {
            return [];
        }

        if ($this->activeApps === null) {
            $this->activeApps = $this->loadApps();
        }

        return $this->activeApps;
    }

    public function reset(): void
    {
        $this->activeApps = null;
    }

    /**
     * @return App[]
     */
    private function loadApps(): array
    {
        try {
            /** @var App[] $apps */
            $apps = $this->connection->fetchAllAssociative('
                SELECT `name`, `path`, `author`
                FROM `app`
                WHERE `active` = 1
            ');

            return $apps;
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
