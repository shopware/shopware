<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1791369987ProductReviewExternalUserNotNull;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1791369987ProductReviewExternalUserNotNull::class)]
class Migration1791369987ProductReviewExternalUserNotNullTest extends TestCase
{
    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $reviewIds = [];

    private ?string $customerId = null;

    private bool $externalUserWasNotNull;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->externalUserWasNotNull = TableHelper::getColumnOfTable($this->connection, 'product_review', 'external_user')->isNotNull;
    }

    protected function tearDown(): void
    {
        foreach ($this->reviewIds as $reviewId) {
            $this->connection->delete('product_review', ['id' => $reviewId]);
        }

        if ($this->customerId !== null) {
            $this->connection->delete('customer', ['id' => $this->customerId]);
        }

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        if (!$this->externalUserWasNotNull) {
            $this->connection->executeStatement(
                'ALTER TABLE `product_review` MODIFY COLUMN `external_user` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL'
            );
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791369987, (new Migration1791369987ProductReviewExternalUserNotNull())->getCreationTimestamp());
    }

    public function testUpdateFillsMissingAuthorsAndMakesTheColumnNotNull(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `product_review` MODIFY COLUMN `external_user` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL'
        );
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        $this->customerId = Uuid::randomBytes();
        $this->connection->insert('customer', [
            'id' => $this->customerId,
            'customer_group_id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::randomBytes(),
            'language_id' => Uuid::randomBytes(),
            'default_billing_address_id' => Uuid::randomBytes(),
            'default_shipping_address_id' => Uuid::randomBytes(),
            'customer_number' => 'review-migration-test',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'review-migration-test@example.com',
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        $customerReview = $this->insertReview(null, $this->customerId);
        $guestReview = $this->insertReview(null, null);
        $namedReview = $this->insertReview('Max', $this->customerId);

        $migration = new Migration1791369987ProductReviewExternalUserNotNull();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame('Jane', $this->fetchExternalUser($customerReview));
        static::assertSame('', $this->fetchExternalUser($guestReview));
        static::assertSame('Max', $this->fetchExternalUser($namedReview));
        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'product_review', 'external_user')->isNotNull);
    }

    private function insertReview(?string $externalUser, ?string $customerId): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('product_review', [
            'id' => $id,
            'product_id' => Uuid::randomBytes(),
            'product_version_id' => Uuid::randomBytes(),
            'customer_id' => $customerId,
            'external_user' => $externalUser,
            'points' => 4.0,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
        $this->reviewIds[] = $id;

        return $id;
    }

    private function fetchExternalUser(string $reviewId): mixed
    {
        return $this->connection->fetchOne('SELECT `external_user` FROM `product_review` WHERE `id` = :id', ['id' => $reviewId]);
    }
}
