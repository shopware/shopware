<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Central storage for app-declared features. The app lifecycle syncs rows from the
 * manifest through syncForApp(); reads return typed AppFeature objects hydrated
 * through the registered definition of the requested feature class.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\App\Feature\AppFeatureStorageTest
 */
#[Package('framework')]
class AppFeatureStorage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly AppFeatureDefinitionRegistry $registry,
    ) {
    }

    /**
     * Features of the given kind declared by active apps.
     *
     * @template T of AppFeatureConfig
     *
     * @param class-string<T> $featureClass
     *
     * @return list<AppFeature<T>>
     */
    public function forActiveApps(string $featureClass): array
    {
        $definition = $this->registry->forFeature($featureClass);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`app_feature`.`app_id`)) AS `app_id`,
                    `app`.`name` AS `app_name`,
                    `app`.`active` AS `app_active`,
                    `app`.`version` AS `app_version`,
                    `app`.`app_secret` IS NOT NULL AS `app_has_secret`,
                    `app_feature`.`created_at` AS `created_at`,
                    `app_feature`.`payload` AS `payload`
             FROM `app_feature`
             INNER JOIN `app` ON `app`.`id` = `app_feature`.`app_id`
             WHERE `app_feature`.`type` = :type AND `app`.`active` = 1
             ORDER BY `app`.`name`, `app_feature`.`name`',
            ['type' => $definition->getType()]
        );

        return $this->hydrate($definition, $rows);
    }

    /**
     * Features of the given kind declared by one app, regardless of the app's
     * active state.
     *
     * @template T of AppFeatureConfig
     *
     * @param class-string<T> $featureClass
     *
     * @return list<AppFeature<T>>
     */
    public function forApp(string $appId, string $featureClass): array
    {
        $definition = $this->registry->forFeature($featureClass);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`app_feature`.`app_id`)) AS `app_id`,
                    `app`.`name` AS `app_name`,
                    `app`.`active` AS `app_active`,
                    `app`.`version` AS `app_version`,
                    `app`.`app_secret` IS NOT NULL AS `app_has_secret`,
                    `app_feature`.`created_at` AS `created_at`,
                    `app_feature`.`payload` AS `payload`
             FROM `app_feature`
             INNER JOIN `app` ON `app`.`id` = `app_feature`.`app_id`
             WHERE `app_feature`.`app_id` = :appId AND `app_feature`.`type` = :type
             ORDER BY `app_feature`.`name`',
            ['appId' => Uuid::fromHexToBytes($appId), 'type' => $definition->getType()]
        );

        return $this->hydrate($definition, $rows);
    }

    /**
     * Brings the app's stored features in line with the given rows: payloads of
     * existing rows are rewritten in place (preserving id and created_at), new rows
     * are inserted, rows no longer present are deleted.
     *
     * @param list<array{type: string, name: string, payload: array<string, mixed>}> $rows
     */
    public function syncForApp(string $appId, string $appName, array $rows): void
    {
        $appIdBytes = Uuid::fromHexToBytes($appId);
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->transactional(function (Connection $connection) use ($appIdBytes, $appName, $rows, $now): void {
            $existing = [];
            $stored = $connection->fetchAllAssociative(
                'SELECT LOWER(HEX(`id`)) AS `id`, `type`, `name` FROM `app_feature` WHERE `app_id` = :appId',
                ['appId' => $appIdBytes]
            );
            foreach ($stored as $row) {
                $existing[$row['type']][$row['name']] = $row['id'];
            }

            foreach ($rows as $row) {
                $id = $existing[$row['type']][$row['name']] ?? null;

                if ($id !== null) {
                    $connection->update('app_feature', [
                        'payload' => Json::encode($row['payload']),
                        'updated_at' => $now,
                    ], ['id' => Uuid::fromHexToBytes($id)]);

                    unset($existing[$row['type']][$row['name']]);

                    continue;
                }

                $connection->insert('app_feature', [
                    'id' => Uuid::randomBytes(),
                    'app_id' => $appIdBytes,
                    'app_name' => $appName,
                    'type' => $row['type'],
                    'name' => $row['name'],
                    'payload' => Json::encode($row['payload']),
                    'created_at' => $now,
                ]);
            }

            $staleIds = [];
            foreach ($existing as $idsByName) {
                foreach ($idsByName as $id) {
                    $staleIds[] = Uuid::fromHexToBytes($id);
                }
            }

            if ($staleIds !== []) {
                $connection->executeStatement(
                    'DELETE FROM `app_feature` WHERE `id` IN (:ids)',
                    ['ids' => $staleIds],
                    ['ids' => ArrayParameterType::BINARY]
                );
            }
        });
    }

    /**
     * Re-attaches rows kept through an uninstall with keepUserData (the foreign key
     * set their app_id to null when the app row was deleted) to a fresh installation
     * of the same app, identified by the app name.
     */
    public function reattachKeptFeatures(string $appId, string $appName): void
    {
        $this->connection->executeStatement(
            'UPDATE `app_feature` SET `app_id` = :appId WHERE `app_id` IS NULL AND `app_name` = :appName',
            ['appId' => Uuid::fromHexToBytes($appId), 'appName' => $appName]
        );
    }

    /**
     * Removes all stored features of the given app.
     */
    public function deleteForApp(string $appId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `app_feature` WHERE `app_id` = :appId',
            ['appId' => Uuid::fromHexToBytes($appId)]
        );
    }

    /**
     * Saves the given config for one declared feature, replacing its payload as is —
     * The feature must already be created by the app: apps declare features, the shop modifies them.
     */
    public function save(string $appId, AppFeatureConfig $config): void
    {
        $definition = $this->registry->forFeature($config::class);
        $name = $config->getName();

        $id = $this->connection->fetchOne(
            'SELECT `id` FROM `app_feature` WHERE `app_id` = :appId AND `type` = :type AND `name` = :name',
            ['appId' => Uuid::fromHexToBytes($appId), 'type' => $definition->getType(), 'name' => $name]
        );

        if ($id === false) {
            throw AppFeatureException::notDeclared($appId, $definition->getType(), $name);
        }

        $this->connection->update(
            'app_feature',
            [
                'payload' => Json::encode($definition->toPayload($config, null)),
                'updated_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['id' => $id]
        );
    }

    /**
     * @template T of AppFeatureConfig
     *
     * @param AppFeatureDefinition<T> $definition
     * @param list<array<string, mixed>> $rows
     *
     * @return list<AppFeature<T>>
     */
    private function hydrate(AppFeatureDefinition $definition, array $rows): array
    {
        return array_map(
            static fn (array $row): AppFeature => new AppFeature(
                (string) $row['app_id'],
                (string) $row['app_name'],
                (bool) $row['app_active'],
                (string) $row['app_version'],
                (bool) $row['app_has_secret'],
                new \DateTimeImmutable((string) $row['created_at']),
                $definition->fromPayload(Json::decodeToArray((string) $row['payload'])),
            ),
            $rows
        );
    }
}
