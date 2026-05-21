<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1778072247AddDocumentBaseConfigTypedColumns;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1778072247AddDocumentBaseConfigTypedColumns::class)]
class Migration1778072247AddDocumentBaseConfigTypedColumnsTest extends TestCase
{
    private const BOOLEAN_COLUMNS = [
        'display_header',
        'display_footer',
        'display_page_count',
        'display_company_address',
        'display_return_address',
        'display_customer_vat_id',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1778072247, (new Migration1778072247AddDocumentBaseConfigTypedColumns())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1778072247AddDocumentBaseConfigTypedColumns();
        $migration->update($this->connection);

        $originalRows = $this->connection->fetchAllAssociativeIndexed(<<<'SQL'
            SELECT `name`, `config`, `page_size`, `page_orientation`, `items_per_page`,
                   `display_header`, `display_footer`, `display_page_count`,
                   `display_company_address`, `display_return_address`, `display_customer_vat_id`
            FROM `document_base_config`
        SQL);

        try {
            $this->connection->executeStatement(<<<'SQL'
                UPDATE `document_base_config`
                SET `page_size` = NULL, `page_orientation` = NULL, `items_per_page` = NULL,
                    `display_header` = NULL, `display_footer` = NULL, `display_page_count` = NULL,
                    `display_company_address` = NULL, `display_return_address` = NULL, `display_customer_vat_id` = NULL
            SQL);

            // Happy path: every typed key is present and well-formed.
            $this->connection->update('document_base_config', [
                'config' => json_encode([
                    'pageSize' => 'A4',
                    'pageOrientation' => 'portrait',
                    'itemsPerPage' => 25,
                    'displayHeader' => true,
                    'displayFooter' => true,
                    'displayPageCount' => true,
                    'displayCompanyAddress' => true,
                    'displayReturnAddress' => true,
                    'displayCustomerVatId' => true,
                ], \JSON_THROW_ON_ERROR),
            ], ['name' => 'invoice']);

            // Mixed invalid: JSON null string, negative items-per-page, all-false booleans.
            $this->connection->update('document_base_config', [
                'config' => json_encode([
                    'pageSize' => null,
                    'itemsPerPage' => -5,
                    'displayHeader' => false,
                    'displayFooter' => false,
                    'displayPageCount' => false,
                    'displayCompanyAddress' => false,
                    'displayReturnAddress' => false,
                    'displayCustomerVatId' => false,
                ], \JSON_THROW_ON_ERROR),
            ], ['name' => 'storno']);

            // All keys absent except zero items-per-page: backfill must leave string/int columns NULL; booleans get NULL too
            // because `JSON_EXTRACT(...) = TRUE` is NULL when the path is missing.
            $this->connection->update('document_base_config', [
                'config' => json_encode([
                    'itemsPerPage' => 0,
                ], \JSON_THROW_ON_ERROR),
            ], ['name' => 'delivery_note']);

            // Null config: backfill is gated by `WHERE config IS NOT NULL`, sentinels must survive.
            $this->connection->update('document_base_config', [
                'config' => null,
                'page_size' => 'preserved',
                'items_per_page' => 99,
                'display_header' => 1,
            ], ['name' => 'credit_note']);

            $migration->update($this->connection);
            $migration->update($this->connection);

            $rows = $this->connection->fetchAllAssociativeIndexed(<<<'SQL'
                SELECT `name`, `page_size`, `page_orientation`, `items_per_page`,
                       `display_header`, `display_footer`, `display_page_count`,
                       `display_company_address`, `display_return_address`, `display_customer_vat_id`
                FROM `document_base_config`
            SQL);

            $invoice = $rows['invoice'];

            static::assertSame('A4', $invoice['page_size']);
            static::assertSame('portrait', $invoice['page_orientation']);
            static::assertSame(25, (int) $invoice['items_per_page']);

            foreach (self::BOOLEAN_COLUMNS as $col) {
                static::assertSame(1, (int) $invoice[$col], $col);
            }

            $cancellation = $rows['storno'];

            static::assertNull($cancellation['page_size']);
            static::assertNull($cancellation['page_orientation']);
            static::assertNull($cancellation['items_per_page']);

            foreach (self::BOOLEAN_COLUMNS as $col) {
                static::assertSame(0, (int) $cancellation[$col], $col);
            }

            $deliveryNote = $rows['delivery_note'];

            static::assertNull($deliveryNote['page_size']);
            static::assertNull($deliveryNote['page_orientation']);
            static::assertNull($deliveryNote['items_per_page']);

            foreach (self::BOOLEAN_COLUMNS as $col) {
                static::assertNull($deliveryNote[$col], $col);
            }

            $creditNote = $rows['credit_note'];

            static::assertSame('preserved', $creditNote['page_size']);
            static::assertSame(99, (int) $creditNote['items_per_page']);
            static::assertSame(1, (int) $creditNote['display_header']);
        } finally {
            $this->restoreRows($originalRows);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     */
    private function restoreRows(array $rows): void
    {
        foreach ($rows as $name => $row) {
            $this->connection->update('document_base_config', [
                'config' => $row['config'],
                'page_size' => $row['page_size'],
                'page_orientation' => $row['page_orientation'],
                'items_per_page' => $row['items_per_page'],
                'display_header' => $row['display_header'],
                'display_footer' => $row['display_footer'],
                'display_page_count' => $row['display_page_count'],
                'display_company_address' => $row['display_company_address'],
                'display_return_address' => $row['display_return_address'],
                'display_customer_vat_id' => $row['display_customer_vat_id'],
            ], ['name' => $name]);
        }
    }
}
