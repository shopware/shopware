<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\User\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\MaintenanceException;
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
                    static::assertSame('admin', $data['username']);
                    static::assertSame('first', $data['first_name']);
                    static::assertSame('last', $data['last_name']);
                    static::assertSame('test@test.com', $data['email']);
                    static::assertSame($localeId, $data['locale_id']);
                    static::assertFalse($data['admin']);
                    static::assertTrue($data['active']);

                    return password_verify('shopware', (string) $data['password']);
                })
            );
        $connection->expects(static::once())->method('fetchOne')->willReturn(json_encode(['_value' => 8], \JSON_THROW_ON_ERROR));
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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User with username "admin" already exists.');
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

        $connection->expects(static::once())->method('fetchOne')->willReturn(json_encode(['_value' => 8], \JSON_THROW_ON_ERROR));

        $user = [
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection);
        $this->expectException(MaintenanceException::class);
        $this->expectExceptionMessage('The password must have at least 8 characters.');
        $provisioner->provision('admin', 'short', $user);
    }
}
