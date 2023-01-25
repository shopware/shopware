<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1586173614AddAdditionalImportExportProfiles extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586173614;
    }

    public function update(Connection $connection): void
    {
        foreach ($this->getProfiles() as $profile) {
            $profile['id'] = Uuid::randomBytes();
            $profile['system_default'] = 1;
            $profile['file_type'] = 'text/csv';
            $profile['delimiter'] = ';';
            $profile['enclosure'] = '"';
            $profile['mapping'] = json_encode($profile['mapping'], \JSON_THROW_ON_ERROR);
            $profile['created_at'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $connection->insert('import_export_profile', $profile);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return list<array{name: string, source_entity: string, mapping: list<array{key: string, mappedKey: string}>}>
     */
    private function getProfiles(): array
    {
        return [
            [
                'name' => 'Default newsletter recipient',
                'source_entity' => NewsletterRecipientDefinition::ENTITY_NAME,
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],
                    ['key' => 'email', 'mappedKey' => 'email'],
                    ['key' => 'title', 'mappedKey' => 'title'],
                    ['key' => 'salutation.salutationKey', 'mappedKey' => 'salutation'],

                    ['key' => 'firstName', 'mappedKey' => 'first_name'],
                    ['key' => 'lastName', 'mappedKey' => 'last_name'],
                    ['key' => 'zipCode', 'mappedKey' => 'zip_code'],
                    ['key' => 'city', 'mappedKey' => 'city'],
                    ['key' => 'street', 'mappedKey' => 'street'],
                    ['key' => 'status', 'mappedKey' => 'status'],
                    ['key' => 'hash', 'mappedKey' => 'hash'],

                    ['key' => 'salesChannel.id', 'mappedKey' => 'sales_channel_id'],
                ],
            ],
            [
                'name' => 'Default variant configuration settings',
                'source_entity' => ProductConfiguratorSettingDefinition::ENTITY_NAME,
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],

                    ['key' => 'productId', 'mappedKey' => 'product_id'],
                    ['key' => 'optionId', 'mappedKey' => 'option_id'],

                    ['key' => 'position', 'mappedKey' => 'position'],

                    ['key' => 'media.id', 'mappedKey' => 'media_id'],
                    ['key' => 'media.url', 'mappedKey' => 'media_url'],
                    ['key' => 'media.mediaFolderId', 'mappedKey' => 'media_folder_id'],
                    ['key' => 'media.mediaType', 'mappedKey' => 'media_type'],
                    ['key' => 'media.translations.DEFAULT.title', 'mappedKey' => 'media_title'],
                    ['key' => 'media.translations.DEFAULT.alt', 'mappedKey' => 'media_alt'],

                    ['key' => 'price.DEFAULT.net', 'mappedKey' => 'price_net'],
                    ['key' => 'price.DEFAULT.gross', 'mappedKey' => 'price_gross'],
                ],
            ],
            [
                'name' => 'Default properties',
                'source_entity' => PropertyGroupOptionDefinition::ENTITY_NAME,
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],
                    ['key' => 'colorHexCode', 'mappedKey' => 'color_hex_code'],
                    ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                    ['key' => 'translations.DEFAULT.position', 'mappedKey' => 'position'],

                    ['key' => 'group.id', 'mappedKey' => 'group_id'],
                    ['key' => 'group.displayType', 'mappedKey' => 'group_display_type'],
                    ['key' => 'group.sortingType', 'mappedKey' => 'group_sorting_type'],
                    ['key' => 'group.translations.DEFAULT.name', 'mappedKey' => 'group_name'],
                    ['key' => 'group.translations.DEFAULT.description', 'mappedKey' => 'group_description'],
                    ['key' => 'group.translations.DEFAULT.position', 'mappedKey' => 'group_position'],

                    ['key' => 'media.id', 'mappedKey' => 'media_id'],
                    ['key' => 'media.url', 'mappedKey' => 'media_url'],
                    ['key' => 'media.mediaFolderId', 'mappedKey' => 'media_folder_id'],
                    ['key' => 'media.mediaType', 'mappedKey' => 'media_type'],
                    ['key' => 'media.translations.DEFAULT.title', 'mappedKey' => 'media_title'],
                    ['key' => 'media.translations.DEFAULT.alt', 'mappedKey' => 'media_alt'],
                ],
            ],
        ];
    }
}
