<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
trait MigrationUntouchedDbTestTrait
{
    private string $databaseName = 'shopware';

    /**
     * @before
     */
    public function setMigrationDb(): void
    {
        $parsedUrl = parse_url((string) $_SERVER['DATABASE_URL']);
        if (!$parsedUrl) {
            throw new \RuntimeException('%DATABASE_URL% can not be parsed, given "' . $_SERVER['DATABASE_URL'] . '".');
        }

        $originalDatabase = $parsedUrl['path'] ?? '';

        $databaseName = $originalDatabase . '_no_migrations';
        $newDbUrl = str_replace($originalDatabase, $databaseName, (string) $_SERVER['DATABASE_URL']);
        putenv('DATABASE_URL=' . $newDbUrl);
        $_ENV['DATABASE_URL'] = $newDbUrl;
        $_SERVER['DATABASE_URL'] = $newDbUrl;
        $this->databaseName = substr($databaseName, 1);
    }

    /**
     * @after
     */
    public function unsetMigrationDb(): void
    {
        $originalDatabase = str_replace('_no_migrations', '', (string) $_SERVER['DATABASE_URL']);
        putenv('DATABASE_URL=' . $originalDatabase);
        $_ENV['DATABASE_URL'] = $originalDatabase;
        $_SERVER['DATABASE_URL'] = $originalDatabase;
    }
}
