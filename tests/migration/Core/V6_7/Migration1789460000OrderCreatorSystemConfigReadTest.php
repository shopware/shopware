<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1789460000OrderCreatorSystemConfigRead;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1789460000OrderCreatorSystemConfigRead::class)]
class Migration1789460000OrderCreatorSystemConfigReadTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1789460000, (new Migration1789460000OrderCreatorSystemConfigRead())->getCreationTimestamp());
    }

    public function testItGrantsTheConfigReadToOrderCreators(): void
    {
        $creator = $this->role(['order.viewer', 'order.editor', 'order.creator', 'order:create']);
        $viewer = $this->role(['order.viewer', 'order:read']);

        $migration = new Migration1789460000OrderCreatorSystemConfigRead();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertContains('system_config:read', $this->privileges($creator));
        static::assertNotContains('system_config:read', $this->privileges($viewer));
        static::assertCount(5, $this->privileges($creator));
    }

    /**
     * @param list<string> $privileges
     */
    private function role(array $privileges): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $id,
            'name' => Uuid::randomHex(),
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /**
     * @return list<string>
     */
    private function privileges(string $id): array
    {
        $privileges = $this->connection->fetchOne('SELECT `privileges` FROM `acl_role` WHERE `id` = :id', ['id' => $id]);

        static::assertIsString($privileges);

        return json_decode($privileges, true, 512, \JSON_THROW_ON_ERROR);
    }
}
