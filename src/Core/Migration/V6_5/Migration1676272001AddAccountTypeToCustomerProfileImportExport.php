<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('customer-order')]
class Migration1676272001AddAccountTypeToCustomerProfileImportExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1676272001;
    }

    public function update(Connection $connection): void
    {
        $this->addAccountTypeToCustomerProfileImportExport($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function addAccountTypeToCustomerProfileImportExport(Connection $connection): void
    {
        /** @var false|array<string, string> $profile */
        $profile = $connection->fetchAssociative(
            'SELECT `id`, `mapping` FROM `import_export_profile` WHERE `source_entity` =:source_entity and `name` = :name AND `system_default` = 1',
            [
                'source_entity' => CustomerDefinition::ENTITY_NAME,
                'name' => 'Default customer',
            ]
        );
        if (!$profile) {
            return;
        }

        $mapping = $this->getCustomerProfileMapping($profile['mapping']);
        $mappingFilterAccountType = array_filter($mapping, function ($mapping) {
            return $mapping['key'] === 'accountType';
        });
        if (\count($mappingFilterAccountType)) {
            return;
        }

        $mapping[] = ['key' => 'accountType', 'mappedKey' => 'account_type'];
        $connection->update('import_export_profile', ['mapping' => json_encode($mapping, \JSON_THROW_ON_ERROR)], ['id' => $profile['id']]);
    }

    /**
     * @return array<array<string, string|int>>
     */
    private function getCustomerProfileMapping(string $mapping): array
    {
        return json_decode($mapping, true, 512, \JSON_THROW_ON_ERROR);
    }
}
