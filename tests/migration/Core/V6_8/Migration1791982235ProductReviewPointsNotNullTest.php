<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1791982235ProductReviewPointsNotNull;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1791982235ProductReviewPointsNotNull::class)]
class Migration1791982235ProductReviewPointsNotNullTest extends TestCase
{
    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $reviewIds = [];

    private bool $pointsWasNotNull;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->pointsWasNotNull = TableHelper::getColumnOfTable($this->connection, 'product_review', 'points')->isNotNull;
    }

    protected function tearDown(): void
    {
        foreach ($this->reviewIds as $reviewId) {
            $this->connection->delete('product_review', ['id' => $reviewId]);
        }

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        if (!$this->pointsWasNotNull) {
            $this->connection->executeStatement('ALTER TABLE `product_review` MODIFY COLUMN `points` DOUBLE NULL');
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791982235, (new Migration1791982235ProductReviewPointsNotNull())->getCreationTimestamp());
    }

    public function testUpdateSetsMissingRatingsToZeroAndMakesTheColumnNotNull(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product_review` MODIFY COLUMN `points` DOUBLE NULL');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        $unratedReview = $this->insertReview(null);
        $ratedReview = $this->insertReview(4.5);

        $migration = new Migration1791982235ProductReviewPointsNotNull();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(0.0, $this->fetchPoints($unratedReview));
        static::assertSame(4.5, $this->fetchPoints($ratedReview));
        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'product_review', 'points')->isNotNull);
    }

    private function insertReview(?float $points): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('product_review', [
            'id' => $id,
            'product_id' => Uuid::randomBytes(),
            'product_version_id' => Uuid::randomBytes(),
            'external_user' => 'Max',
            'points' => $points,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
        $this->reviewIds[] = $id;

        return $id;
    }

    private function fetchPoints(string $reviewId): float
    {
        $points = $this->connection->fetchOne('SELECT `points` FROM `product_review` WHERE `id` = :id', ['id' => $reviewId]);

        static::assertNotNull($points);

        return (float) $points;
    }
}
