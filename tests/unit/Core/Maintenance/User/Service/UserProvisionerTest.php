<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\User\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
use Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Helpers\Fakes\FakeQueryBuilder;

/**
 * @internal
 */
#[CoversClass(UserProvisioner::class)]
class UserProvisionerTest extends TestCase
{
    public function testProvision(): void
    {
        $localeId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);

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

                    return password_verify('shopware', (string) $data['password']);
                })
            );
        $connection->expects(static::once())->method('fetchOne')->willReturn(json_encode(['_value' => 8]));
        $connection->expects(static::exactly(2))->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection);
        $provisioner->provision('admin', 'shopware', $user);
    }

    public function testProvisionThrowsIfUserAlreadyExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())
            ->method('insert');

        $connection->expects(static::once())->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, [[Uuid::randomBytes()]]),
        );

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection);
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('User with username "admin" already exists.');
        $provisioner->provision('admin', 'shopware', $user);
    }

    public function testProvisionThrowsIfPasswordTooShort(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())
            ->method('insert');

        $connection->expects(static::once())->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
        );

        $connection->expects(static::once())->method('fetchOne')->willReturn(json_encode(['_value' => 8]));

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection);
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The password length cannot be shorter than 8 characters.');
        $provisioner->provision('admin', 'short', $user);
    }

    public function testProvisionGeneratePasswordIfNullPasswordGiven(): void
    {
        $localeId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);

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

        $connection->expects(static::once())->method('fetchOne')->willReturn(json_encode(['_value' => 8]));
        $connection->expects(static::exactly(2))->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection);
        $provisioner->provision('admin', null, $user);
    }
}
