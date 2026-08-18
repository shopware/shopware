<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1778072247AddDocumentBaseConfigTypedColumns extends MigrationStep
{
    use AddColumnTrait;

    final public const TYPES_COLUMNS = [
        'page_size' => 'VARCHAR(32)',
        'page_orientation' => 'VARCHAR(32)',
        'items_per_page' => 'INT UNSIGNED',
        'display_header' => 'TINYINT(1)',
        'display_footer' => 'TINYINT(1)',
        'display_page_count' => 'TINYINT(1)',
        'display_company_address' => 'TINYINT(1)',
        'display_return_address' => 'TINYINT(1)',
        'display_customer_vat_id' => 'TINYINT(1)',
    ];

    public function getCreationTimestamp(): int
    {
        return 1778072247;
    }

    public function update(Connection $connection): void
    {
        foreach (self::TYPES_COLUMNS as $column => $type) {
            $this->addColumn(
                $connection,
                DocumentBaseConfigDefinition::ENTITY_NAME,
                $column,
                $type,
            );
        }

        $this->backfillStringColumns($connection);
        $this->backfillIntegerColumns($connection);
        $this->backfillBooleanColumns($connection);
    }

    private function backfillStringColumns(Connection $connection): void
    {
        // JSON_TYPE = 'STRING' guard skips JSON null and absent keys — otherwise
        // JSON_UNQUOTE(JSON_EXTRACT(...)) yields the literal string "null".
        $connection->executeStatement(<<<'SQL'
            UPDATE `document_base_config`
            SET
                `page_size` = CASE
                    WHEN JSON_TYPE(JSON_EXTRACT(`config`, '$.pageSize')) = 'STRING'
                        THEN JSON_UNQUOTE(JSON_EXTRACT(`config`, '$.pageSize'))
                    ELSE `page_size`
                END,
                `page_orientation` = CASE
                    WHEN JSON_TYPE(JSON_EXTRACT(`config`, '$.pageOrientation')) = 'STRING'
                        THEN JSON_UNQUOTE(JSON_EXTRACT(`config`, '$.pageOrientation'))
                    ELSE `page_orientation`
                END
            WHERE `config` IS NOT NULL
        SQL);
    }

    private function backfillIntegerColumns(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            UPDATE `document_base_config`
            SET `items_per_page` = CAST(JSON_EXTRACT(`config`, '$.itemsPerPage') AS UNSIGNED)
            WHERE `config` IS NOT NULL
              AND JSON_TYPE(JSON_EXTRACT(`config`, '$.itemsPerPage')) = 'INTEGER'
              AND CAST(JSON_EXTRACT(`config`, '$.itemsPerPage') AS SIGNED) >= 1
        SQL);
    }

    private function backfillBooleanColumns(Connection $connection): void
    {
        // JSON_TYPE = 'BOOLEAN' guard keeps re-runs idempotent: if the JSON path is missing,
        // preserve whatever the column already holds instead of clobbering it to NULL.
        $connection->executeStatement(<<<'SQL'
            UPDATE `document_base_config`
            SET
                `display_header` = CASE
                    WHEN JSON_TYPE(JSON_EXTRACT(`config`, '$.displayHeader')) = 'BOOLEAN'
                        THEN (JSON_EXTRACT(`config`, '$.displayHeader') = TRUE)
                    ELSE `display_header`
                END,
                `display_footer` = CASE
                    WHEN JSON_TYPE(JSON_EXTRACT(`config`, '$.displayFooter')) = 'BOOLEAN'
                        THEN (JSON_EXTRACT(`config`, '$.displayFooter') = TRUE)
                    ELSE `display_footer`
                END,
                `display_page_count` = CASE
                    WHEN JSON_TYPE(JSON_EXTRACT(`config`, '$.displayPageCount')) = 'BOOLEAN'
                        THEN (JSON_EXTRACT(`config`, '$.displayPageCount') = TRUE)
                    ELSE `display_page_count`
                END,
                `display_company_address` = CASE
                    WHEN JSON_TYPE(JSON_EXTRACT(`config`, '$.displayCompanyAddress')) = 'BOOLEAN'
                        THEN (JSON_EXTRACT(`config`, '$.displayCompanyAddress') = TRUE)
                    ELSE `display_company_address`
                END,
                `display_return_address` = CASE
                    WHEN JSON_TYPE(JSON_EXTRACT(`config`, '$.displayReturnAddress')) = 'BOOLEAN'
                        THEN (JSON_EXTRACT(`config`, '$.displayReturnAddress') = TRUE)
                    ELSE `display_return_address`
                END,
                `display_customer_vat_id` = CASE
                    WHEN JSON_TYPE(JSON_EXTRACT(`config`, '$.displayCustomerVatId')) = 'BOOLEAN'
                        THEN (JSON_EXTRACT(`config`, '$.displayCustomerVatId') = TRUE)
                    ELSE `display_customer_vat_id`
                END
            WHERE `config` IS NOT NULL
        SQL);
    }
}
