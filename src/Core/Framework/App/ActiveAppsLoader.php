<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type App array{name: string, path: string, author: string|null}
 */
#[Package('core')]
class ActiveAppsLoader implements ResetInterface
{
    /**
     * @var array<App>|null
     */
    private ?array $activeApps = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractAppLoader $appLoader,
        private readonly string $projectDir
    ) {
    }

    /**
     * @return array<App>
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
     * @return array<App>
     */
    private function loadApps(): array
    {
        try {
            /** @phpstan-ignore-next-line PHPStan could not recognize the loaded array shape from the database */
            return $this->connection->fetchAllAssociative('
                SELECT `name`, `path`, `author`
                FROM `app`
                WHERE `active` = 1
            ');
        } catch (\Throwable $e) {
            if (\defined('\STDERR')) {
                fwrite(\STDERR, 'Warning: Failed to load apps. Loading apps from local. Message: ' . $e->getMessage() . \PHP_EOL);
            }

            return array_map(fn (Manifest $manifest) => [
                'name' => $manifest->getMetadata()->getName(),
                'path' => Path::makeRelative($manifest->getPath(), $this->projectDir),
                'author' => $manifest->getMetadata()->getAuthor(),
            ], $this->appLoader->load());
        }
    }
}
