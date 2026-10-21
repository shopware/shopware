<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
        return ($this->createAppContextFactory($hook, $script))();
    }

    /**
     * Returns a factory that builds a fresh app Context, with its own non-admin AdminApiSource, on every call.
     * Facades must obtain a new context per operation so a script cannot escalate one shared source and reuse it.
     *
     * @return \Closure(): Context
     */
    public function createAppContextFactory(Hook $hook, Script $script): \Closure
    {
        $scriptAppInformation = $script->getScriptAppInformation();
        if (!$scriptAppInformation) {
            $context = $hook->getContext();

            return static fn (): Context => $context;
        }

        $hookContext = $hook->getContext();
        $privileges = $this->getPrivileges($scriptAppInformation->getAppId());
        $integrationId = $scriptAppInformation->getIntegrationId();

        // Snapshot the hook-derived context values once; only the source is rebuilt per operation.
        $ruleIds = $hookContext->getRuleIds();
        $currencyId = $hookContext->getCurrencyId();
        $languageIdChain = $hookContext->getLanguageIdChain();
        $versionId = $hookContext->getVersionId();
        $currencyFactor = $hookContext->getCurrencyFactor();
        $considerInheritance = $hookContext->considerInheritance();
        $taxState = $hookContext->getTaxState();
        $rounding = $hookContext->getRounding();

        return static function () use (
            $privileges,
            $integrationId,
            $ruleIds,
            $currencyId,
            $languageIdChain,
            $versionId,
            $currencyFactor,
            $considerInheritance,
            $taxState,
            $rounding
        ): Context {
            $source = new AdminApiSource(null, $integrationId);
            $source->setIsAdmin(false);
            $source->setPermissions($privileges);

            return new Context(
                $source,
                $ruleIds,
                $currencyId,
                $languageIdChain,
                $versionId,
                $currencyFactor,
                $considerInheritance,
                $taxState,
                clone $rounding
            );
        };
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
            return [];
        }

        return json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);
    }
}
