<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1730790665ElectronicInvoice;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1730790665ElectronicInvoice::class)]
class Migration1730790665ElectronicInvoiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $id = $this->connection->fetchOne('SELECT `id` FROM `number_range_type` WHERE `technical_name` = \'document_zugferd_invoice\'');

        $this->connection->delete('document_type', ['technical_name' => ZugferdRenderer::TYPE]);
        $this->connection->delete('document_type', ['technical_name' => ZugferdEmbeddedRenderer::TYPE]);
        $this->connection->delete('number_range_type', ['technical_name' => 'document_zugferd_invoice']);

        // TODO: number range must be clarified
        if ($id) {
            $this->connection->delete('number_range', ['type_id' => $id]);
        }

        $this->connection->fetchAllAssociative('SELECT count(*) FROM number_range');

        $migration = new Migration1730790665ElectronicInvoice();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $documentTypes = $this->connection
            ->executeQuery('SELECT `id` FROM `document_type` WHERE `technical_name` LIKE \'%zugferd%\'')
            ->fetchAllAssociative();
        $numberRange = $this->connection
            ->executeQuery('SELECT `id` FROM `number_range_type` WHERE `technical_name` LIKE \'%zugferd%\'')
            ->fetchAllAssociative();

        static::assertCount(2, $documentTypes);
        static::assertCount(1, $numberRange);
    }
}
