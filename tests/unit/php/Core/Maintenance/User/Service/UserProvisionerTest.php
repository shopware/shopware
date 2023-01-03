<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\User\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes\FakeQueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 * @covers \Shopware\Core\Maintenance\User\Service\UserProvisioner
 */
class UserProvisionerTest extends TestCase
{
    public function testProvision(): void
    {
        $localeId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);
        $systemConfig = $this->createMock(SystemConfigService::class);

        $connection->expects(static::once())
            ->method('insert')
            ->with(
                'user',
                static::callback(static function (array $data) use ($localeId): bool {
                    static::assertEquals('admin', $data['username']);
                    static::assertEquals('first', $data['first_name']);
                    static::assertEquals('last', $data['last_name']);
                    static::assertEquals('test@test.com', $data['email']);
                    static::assertEquals($localeId, $data['locale_id']);
                    static::assertFalse($data['admin']);
                    static::assertTrue($data['active']);

                    return password_verify('shopware', $data['password']);
                })
            );

        $connection->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection, $systemConfig);
        $provisioner->provision('admin', 'shopware', $user);
    }

    public function testProvisionThrowsIfUserAlreadyExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())
            ->method('insert');

        $systemConfig = $this->createMock(SystemConfigService::class);

        $connection->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, [[Uuid::randomBytes()]]),
        );

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection, $systemConfig);
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('User with username "admin" already exists.');
        $provisioner->provision('admin', 'shopware', $user);
    }

    public function testProvisionThrowsIfPasswordTooShort(): void
    {
        $connection = $this->createMock(Connection::class);
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects(static::once())->method('getInt')->willReturn(8);
        $connection->expects(static::never())
            ->method('insert');

        $connection->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
        );

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection, $systemConfig);
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The password length cannot be shorter than 8 characters.');
        $provisioner->provision('admin', 'short', $user);
    }

    public function testProvisionGeneratePasswordIfNullPasswordGiven(): void
    {
        $localeId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects(static::once())->method('getInt')->willReturn(20);

        $connection->expects(static::once())
            ->method('insert')
            ->with(
                'user',
                static::callback(static function (array $data) use ($localeId): bool {
                    static::assertEquals('admin', $data['username']);
                    static::assertEquals('first', $data['first_name']);
                    static::assertEquals('last', $data['last_name']);
                    static::assertEquals('test@test.com', $data['email']);
                    static::assertEquals($localeId, $data['locale_id']);
                    static::assertFalse($data['admin']);
                    static::assertTrue($data['active']);

                    return true;
                })
            );

        $connection->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection, $systemConfig);
        $provisioner->provision('admin', null, $user);
    }
}
