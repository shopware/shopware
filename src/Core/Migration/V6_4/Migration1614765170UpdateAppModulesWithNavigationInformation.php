<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1614765170UpdateAppModulesWithNavigationInformation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614765170;
    }

    public function update(Connection $connection): void
    {
        $apps = $connection->executeQuery('SELECT `id`, `modules` FROM `app`')->fetchAll(FetchMode::ASSOCIATIVE);

        $preparedModules = $this->prepareModules($apps);

        $this->updateModules($preparedModules, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function prepareModules(array $apps): array
    {
        return array_map(static function (array $app) {
            if (!$app['modules']) {
                return $app;
            }

            $modules = json_decode($app['modules'], true);

            if (!\is_array($modules)) {
                return $app;
            }

            foreach ($modules as &$module) {
                $module['parent'] ??= null;
                $module['position'] ??= 1;
            }

            return [
                'id' => $app['id'],
                'modules' => json_encode($modules),
            ];
        }, $apps);
    }

    private function updateModules(array $preparedModules, Connection $connection): void
    {
        $connection->beginTransaction();

        $statement = $connection->prepare('UPDATE `app` SET `modules` = :modules WHERE `id` = :id');

        try {
            foreach ($preparedModules as $prepared) {
                $statement->execute([
                    'id' => $prepared['id'],
                    'modules' => $prepared['modules'],
                ]);
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }
}
