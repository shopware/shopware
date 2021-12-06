<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\System\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

class DatabaseConnectionInformationTest extends TestCase
{
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
