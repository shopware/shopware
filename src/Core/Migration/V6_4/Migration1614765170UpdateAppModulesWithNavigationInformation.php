<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1614765170UpdateAppModulesWithNavigationInformation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614765170;
    }

    public function update(Connection $connection): void
    {
        /** @var list<array{id: string, modules: string|null}> $apps */
        $apps = $connection->executeQuery('SELECT `id`, `modules` FROM `app`')->fetchAllAssociative();

        $preparedModules = $this->prepareModules($apps);

        $this->updateModules($preparedModules, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @param list<array{id: string, modules: string|null}> $apps
     *
     * @return list<array{id: string, modules: string|null}>
     */
    private function prepareModules(array $apps): array
    {
        return array_map(static function (array $app) {
            if (!$app['modules']) {
                return $app;
            }

            $modules = json_decode((string) $app['modules'], true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($modules)) {
                return $app;
            }

            foreach ($modules as &$module) {
                $module['parent'] ??= null;
                $module['position'] ??= 1;
            }

            return [
                'id' => $app['id'],
                'modules' => json_encode($modules, \JSON_THROW_ON_ERROR),
            ];
        }, $apps);
    }

    /**
     * @param list<array{id: string, modules: string|null}> $preparedModules
     */
    private function updateModules(array $preparedModules, Connection $connection): void
    {
        $connection->beginTransaction();

        $statement = $connection->prepare('UPDATE `app` SET `modules` = :modules WHERE `id` = :id');

        try {
            foreach ($preparedModules as $prepared) {
                $statement->executeStatement([
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
