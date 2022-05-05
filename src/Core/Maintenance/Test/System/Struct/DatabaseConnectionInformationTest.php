<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\System\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 */
class DatabaseConnectionInformationTest extends TestCase
{
    use EnvTestBehaviour;

    public function testItDecodesSpecialCharsInDbPasswordsFromEnv(): void
    {
        $this->setEnvVars(['DATABASE_URL' => 'mysql://user:ultra%3Fsecure%23@mysql:3306/test_db']);

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

    public function testItAllowsAnEmptyDbPassword(): void
    {
        $this->setEnvVars(['DATABASE_URL' => 'mysql://user:@mysql:3306/test_db']);

        $dbConnectionInformation = DatabaseConnectionInformation::fromEnv();

        static::assertEquals('mysql://user:@mysql:3306/test_db', $dbConnectionInformation->asDsn());
    }
}
