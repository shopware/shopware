<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1698682149MakeTranslatableFieldsNullable extends MigrationStep
{
    /**
     * @var array<string, array<string>>
     */
    public array $toUpdate = [
        'app_translation' => [
            'label',
        ],
        'app_action_button_translation' => [
            'label',
        ],
        'app_script_condition_translation' => [
            'name',
        ],
        'app_cms_block_translation' => [
            'label',
        ],
        'app_flow_action_translation' => [
            'label',
        ],
        'country_translation' => [
            'address_format',
        ],
        'tax_rule_type_translation' => [
            'type_name',
        ],
        'state_machine_translation' => [
            'name',
        ],
        'state_machine_state_translation' => [
            'name',
        ],
        'number_range_type_translation' => [
            'type_name',
        ],
        'product_cross_selling_translation' => [
            'name',
        ],
        'product_feature_set_translation' => [
            'name',
        ],
        'mail_template_type_translation' => [
            'name',
        ],
        'document_type_translation' => [
            'name',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1698682149;
    }

    public function update(Connection $connection): void
    {
        foreach ($this->toUpdate as $table => $columns) {
            foreach ($columns as $column) {
                $type = $this->getFieldType($connection, $table, $column);

                $connection->executeStatement(
                    sprintf(
                        'ALTER TABLE `%s` MODIFY `%s` %s DEFAULT NULL;',
                        $table,
                        $column,
                        $type
                    )
                );
            }
        }
    }

    private function getFieldType(Connection $connection, string $table, string $column): string
    {
        /** @var array{Type: string} $row */
        $row = $connection->fetchAssociative('SHOW COLUMNS FROM ' . $table . ' WHERE Field = ?', [$column]);

        return $row['Type'];
    }
}
