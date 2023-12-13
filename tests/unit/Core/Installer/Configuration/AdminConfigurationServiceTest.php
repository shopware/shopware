<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Installer\Configuration\AdminConfigurationService;
use Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Helpers\Fakes\FakeQueryBuilder;

/**
 * @internal
 */
#[CoversClass(AdminConfigurationService::class)]
class AdminConfigurationServiceTest extends TestCase
{
    public function testCreateAdmin(): void
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
                    static::assertTrue($data['admin']);
                    static::assertTrue($data['active']);

                    return password_verify('shopware', (string) $data['password']);
                })
            );

        $connection->expects(static::once())->method('fetchOne')->willReturn(json_encode(['_value' => 8]));

        $connection->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $user = [
            'username' => 'admin',
            'password' => 'shopware',
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
        ];

        $service = new AdminConfigurationService();
        $service->createAdmin($user, $connection);
    }
}
