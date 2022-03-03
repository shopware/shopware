<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\System\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

class DatabaseConnectionInformationTest extends TestCase
{
    /**
     * @backupGlobals enabled
     */
    public function testItDecodesSpecialCharsInDbPasswordsFromEnv(): void
    {
        unset($_SERVER['DATABASE_URL'], $_ENV['DATABASE_URL']);
        putenv('DATABASE_URL=mysql://user:ultra%3Fsecure%23@mysql:3306/test_db');
        $dbConnectionInformation = DatabaseConnectionInformation::fromEnv();

        static::assertEquals('ultra?secure#', $dbConnectionInformation->getPassword());
    }

    public function testItEncodesSpecialCharsInDbPasswords(): void
    {
        $dbConnectionInformation = (new DatabaseConnectionInformation())->assign([
            'hostname' => 'mysql',
            'username' => 'user',
            'password' => 'ultra?secure#',
            'databaseName' => 'test_db',
        ]);

        static::assertEquals('mysql://user:ultra%3Fsecure%23@mysql:3306/test_db', $dbConnectionInformation->asDsn());
    }
}
