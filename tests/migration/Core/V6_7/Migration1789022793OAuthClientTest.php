<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1789022793OAuthClient;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1789022793OAuthClient::class)]
class Migration1789022793OAuthClientTest extends TestCase
{
    public function testCreatesTableIdempotently(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1789022793OAuthClient();
        $migration->update($connection);
        $migration->update($connection);

        static::assertSame(1789022793, $migration->getCreationTimestamp());
        static::assertSame(['id', 'name', 'active', 'redirect_uris', 'created_at', 'updated_at'], array_column(
            $connection->fetchAllAssociative('SHOW COLUMNS FROM `oauth_client`'),
            'Field',
        ));
    }
}
