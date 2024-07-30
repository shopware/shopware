<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1721202771UpdateDefaultSearchConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1721202771;
    }

    public function update(Connection $connection): void
    {
        $configs = $connection->fetchAllAssociative(
            'SELECT `id`, `value` FROM `user_config` WHERE `key` = :key',
            ['key' => 'search.preferences']
        );

        foreach ($configs as $record) {
            if ($record['value'] === null) {
                continue;
            }

            try {
                $config = json_decode((string) $record['value'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!\is_array($config)) {
                continue;
            }

            foreach ($config as $index => $item) {
                if (!\array_key_exists('media', $item)) {
                    continue;
                }

                $item['media']['path'] = [
                    '_score' => 500,
                    '_searchable' => true,
                ];

                $config[$index] = $item;
            }

            $connection->executeStatement(
                'UPDATE `user_config` SET `value` = :value WHERE `id` = :id',
                ['id' => $record['id'], 'value' => json_encode($config)]
            );
        }
    }
}
