<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1764580028AddAltAttributeToOrderConfirmationMailImages;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1764580028AddAltAttributeToOrderConfirmationMailImages::class)]
class Migration1764580028AddAltAttributeToOrderConfirmationMailImagesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationOfUnmodifiedTranslation(): void
    {
        $migration = new Migration1764580028AddAltAttributeToOrderConfirmationMailImages();

        $error = [];
        try {
            $migration->update($this->connection);
            $migration->update($this->connection);
        } catch (\Throwable $e) {
            $error[] = $e->getMessage();
        }

        static::assertCount(0, $error, print_r($error, true));
    }
}
