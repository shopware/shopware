<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\AppContextFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class AppContextCreator
{
    /**
     * @var array<string, list<string>>
     */
    private array $appPrivileges = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getAppContext(Hook $hook, Script $script): Context
    {
        return $this->createAppContextFactory($hook, $script)->create();
    }

    /**
     * Facades must obtain a new context per operation, so a script cannot change one shared context and reuse it.
     */
    public function createAppContextFactory(Hook $hook, Script $script): AppContextFactory
    {
        $scriptAppInformation = $script->getScriptAppInformation();
        if (!$scriptAppInformation) {
            return AppContextFactory::forHookContext($hook->getContext());
        }

        return AppContextFactory::forApp(
            $hook->getContext(),
            $scriptAppInformation->getIntegrationId(),
            $this->getPrivileges($scriptAppInformation->getAppId())
        );
    }

    /**
     * @return list<string>
     */
    private function getPrivileges(string $appId): array
    {
        return $this->appPrivileges[$appId] ??= $this->fetchPrivileges($appId);
    }

    /**
     * @return list<string>
     */
    private function fetchPrivileges(string $appId): array
    {
        $privileges = $this->connection->fetchOne('
            SELECT `acl_role`.`privileges` AS `privileges`
            FROM `acl_role`
            INNER JOIN `app` ON `app`.`acl_role_id` = `acl_role`.`id`
            WHERE `app`.`id` = :appId
        ', ['appId' => Uuid::fromHexToBytes($appId)]);

        if (!$privileges) {
            throw new \RuntimeException(\sprintf('Privileges for app with id "%s" not found.', $appId));
        }

        return json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);
    }
}
