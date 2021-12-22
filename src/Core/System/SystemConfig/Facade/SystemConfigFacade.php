<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Facade;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SystemConfigFacade
{
    private const PRIVILEGE = 'system_config:read';

    private SystemConfigService $systemConfigService;

    private Connection $connection;

    private ?string $appId;

    private ?string $salesChannelId;

    private array $appData = [];

    public function __construct(SystemConfigService $systemConfigService, Connection $connection, ?string $appId, ?string $salesChannelId)
    {
        $this->systemConfigService = $systemConfigService;
        $this->connection = $connection;
        $this->appId = $appId;
        $this->salesChannelId = $salesChannelId;
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function get(string $key, ?string $salesChannelId = null)
    {
        if (!$salesChannelId) {
            $salesChannelId = $this->salesChannelId;
        }

        if ($this->appId) {
            $privileges = $this->fetchAppData($this->appId)['privileges'];

            if (!\in_array(self::PRIVILEGE, $privileges, true)) {
                throw new MissingPrivilegeException([self::PRIVILEGE]);
            }
        }

        return $this->systemConfigService->get($key, $salesChannelId);
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function app(string $key, ?string $salesChannelId = null)
    {
        if (!$this->appId) {
            throw new \BadMethodCallException('`config.app()` can only be called from app scripts.');
        }

        if (!$salesChannelId) {
            $salesChannelId = $this->salesChannelId;
        }

        $appName = $this->fetchAppData($this->appId)['name'];
        $key = $appName . '.config.' . $key;

        return $this->systemConfigService->get($key, $salesChannelId);
    }

    private function fetchAppData(string $appId): array
    {
        if (\array_key_exists($appId, $this->appData)) {
            return $this->appData[$appId];
        }

        $data = $this->connection->fetchAssociative('
            SELECT `acl_role`.`privileges` AS `privileges`, `app`.`name` AS `name`
            FROM `acl_role`
            INNER JOIN `app` ON `app`.`acl_role_id` = `acl_role`.`id`
            WHERE `app`.`id` = :appId
        ', ['appId' => Uuid::fromHexToBytes($appId)]);

        if (!$data) {
            throw new \RuntimeException(sprintf('Privileges for app with id "%s" not found.', $appId));
        }

        return $this->appData[$appId] = [
            'privileges' => json_decode($data['privileges'] ?? '[]', true),
            'name' => $data['name'],
        ];
    }
}
