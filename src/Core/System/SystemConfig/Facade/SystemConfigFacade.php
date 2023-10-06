<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Facade;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * The `config` service allows you to access the shop's and your app's configuration values.
 *
 * @script-service miscellaneous
 */
#[Package('system-settings')]
class SystemConfigFacade
{
    private const PRIVILEGE = 'system_config:read';

    private array $appData = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly ?ScriptAppInformation $scriptAppInformation,
        private readonly ?string $salesChannelId
    ) {
    }

    /**
     * The `get()` method allows you to access all config values of the store.
     * Notice that your app needs the `system_config:read` privilege to use this method.
     *
     * @param string $key The key of the configuration value e.g. `core.listing.defaultSorting`.
     * @param string|null $salesChannelId The SalesChannelId if you need the config value for a specific SalesChannel, if you don't provide a SalesChannelId, the one of the current Context is used as default.
     *
     * @return array|bool|float|int|string|null
     *
     * @example test-config/script.twig 4 1 Read an arbitrary system_config value.
     */
    public function get(string $key, ?string $salesChannelId = null)
    {
        if (!$salesChannelId) {
            $salesChannelId = $this->salesChannelId;
        }

        if ($this->scriptAppInformation) {
            $privileges = $this->fetchAppPrivileges($this->scriptAppInformation->getAppId());

            if (!\in_array(self::PRIVILEGE, $privileges, true)) {
                throw new MissingPrivilegeException([self::PRIVILEGE]);
            }
        }

        return $this->systemConfigService->get($key, $salesChannelId);
    }

    /**
     * The `app()` method allows you to access the config values your app's configuration.
     * Notice that your app does not need any additional privileges to use this method, as you can only access your own app's configuration.
     *
     * @param string $key The name of the configuration value specified in the config.xml e.g. `exampleTextField`.
     * @param string|null $salesChannelId The SalesChannelId if you need the config value for a specific SalesChannel, if you don't provide a SalesChannelId, the one of the current Context is used as default.
     *
     * @return array|bool|float|int|string|null
     *
     * @example test-config/script.twig 5 1 Read your app's config value.
     */
    public function app(string $key, ?string $salesChannelId = null)
    {
        if (!$this->scriptAppInformation) {
            throw new \BadMethodCallException('`config.app()` can only be called from app scripts.');
        }

        if (!$salesChannelId) {
            $salesChannelId = $this->salesChannelId;
        }

        $key = $this->scriptAppInformation->getAppName() . '.config.' . $key;

        return $this->systemConfigService->get($key, $salesChannelId);
    }

    private function fetchAppPrivileges(string $appId): array
    {
        if (\array_key_exists($appId, $this->appData)) {
            return $this->appData[$appId];
        }

        $privileges = $this->connection->fetchOne('
            SELECT `acl_role`.`privileges` AS `privileges`
            FROM `acl_role`
            INNER JOIN `app` ON `app`.`acl_role_id` = `acl_role`.`id`
            WHERE `app`.`id` = :appId
        ', ['appId' => Uuid::fromHexToBytes($appId)]);

        if (!$privileges) {
            throw new \RuntimeException(sprintf('Privileges for app with id "%s" not found.', $appId));
        }

        return $this->appData[$appId] = json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);
    }
}
