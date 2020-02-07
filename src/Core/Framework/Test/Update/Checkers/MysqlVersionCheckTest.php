<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Checkers;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Update\Checkers\MysqlVersionCheck;

class MysqlVersionCheckTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCheck(): void
    {
        $validationResult = (new MysqlVersionCheck($this->getContainer()->get(Connection::class)))->check('5.7.21');

        $validationResultArray = json_decode(json_encode($validationResult), true);

        static::assertTrue($validationResultArray['result']);
    }

    public function testCheckInvalid(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->method('fetchColumn')->willReturn('5.7.0');
        $validationResult = (new MysqlVersionCheck($connectionMock))->check('5.7.21');

        $validationResultArray = json_decode(json_encode($validationResult), true);

        static::assertFalse($validationResultArray['result']);
    }

    public function testSupports(): void
    {
        $check = new MysqlVersionCheck($this->createMock(Connection::class));

        static::assertTrue($check->supports('mysqlversion'));
        static::assertFalse($check->supports('phpversion'));
        static::assertFalse($check->supports('licensecheck'));
        static::assertFalse($check->supports('writable'));
        static::assertFalse($check->supports(''));
    }
}
