<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class AppContextCreator
{
    private Connection $connection;

    /**
     * @var array<string, AdminApiSource>
     */
    private array $appSources = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getAppContext(Hook $hook, Script $script): Context
    {
        if (!$script->getAppId()) {
            return $hook->getContext();
        }

        return new Context(
            $this->getAppContextSource($script->getAppId()),
            $hook->getContext()->getRuleIds(),
            $hook->getContext()->getCurrencyId(),
            $hook->getContext()->getLanguageIdChain(),
            $hook->getContext()->getVersionId(),
            $hook->getContext()->getCurrencyFactor(),
            $hook->getContext()->considerInheritance(),
            $hook->getContext()->getTaxState(),
            $hook->getContext()->getRounding()
        );
    }

    private function getAppContextSource(string $appId): AdminApiSource
    {
        if (\array_key_exists($appId, $this->appSources)) {
            return $this->appSources[$appId];
        }

        $data = $this->fetchPrivileges($appId);
        $source = new AdminApiSource(null, $data['integrationId']);
        $source->setIsAdmin(false);
        $source->setPermissions($data['privileges']);

        return $this->appSources[$appId] = $source;
    }

    private function fetchPrivileges(string $appId): array
    {
        $data = $this->connection->fetchAssociative('
            SELECT `acl_role`.`privileges` AS `privileges`, `app`.`integration_id` AS `integrationId`
            FROM `acl_role`
            INNER JOIN `app` ON `app`.`acl_role_id` = `acl_role`.`id`
            WHERE `app`.`id` = :appId
        ', ['appId' => Uuid::fromHexToBytes($appId)]);

        if (!$data) {
            throw new \RuntimeException(sprintf('Privileges for app with id "%s" not found.', $appId));
        }

        return [
            'privileges' => json_decode($data['privileges'] ?? '[]', true),
            'integrationId' => Uuid::fromBytesToHex($data['integrationId']),
        ];
    }
}
