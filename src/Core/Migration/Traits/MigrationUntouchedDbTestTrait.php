<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

trait MigrationUntouchedDbTestTrait
{
    private $databaseName = 'shopware';

    /**
     * @before
     */
    public function setMigrationDb(): void
    {
        $originalDatabase = parse_url($_SERVER['DATABASE_URL'])['path'];

        $databaseName = $originalDatabase . '_no_migrations';
        $newDbUrl = str_replace($originalDatabase, $databaseName, $_SERVER['DATABASE_URL']);
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
        $originalDatabase = str_replace('_no_migrations', '', $_SERVER['DATABASE_URL']);
        putenv('DATABASE_URL=' . $originalDatabase);
        $_ENV['DATABASE_URL'] = $originalDatabase;
        $_SERVER['DATABASE_URL'] = $originalDatabase;
    }
}
