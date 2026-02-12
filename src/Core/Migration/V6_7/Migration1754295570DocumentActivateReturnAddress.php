<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1754295570DocumentActivateReturnAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1754295570;
    }

    public function update(Connection $connection): void
    {
        $documentConfigData = $connection->executeQuery('SELECT `id`, `config` FROM `document_base_config`;')->fetchAllAssociative();

        $documentConfig = array_map(function ($arr): array {
            if (!\array_key_exists('config', $arr) || !\is_string($arr['config'])) {
                $arr['config'] = [];
                $arr['config']['displayReturnAddress'] = true;
                $arr['config'] = json_encode($arr['config']);

                return $arr;
            }

            $arr['config'] = json_decode($arr['config'], true, 512, \JSON_THROW_ON_ERROR);
            $arr['config']['displayReturnAddress'] = true;
            $arr['config'] = json_encode($arr['config']);

            return $arr;
        }, $documentConfigData);

        array_walk(
            $documentConfig,
            function (array $arr) use ($connection): void {
                $connection->update('document_base_config', ['config' => $arr['config']], ['id' => $arr['id']]);
            }
        );
    }
}
