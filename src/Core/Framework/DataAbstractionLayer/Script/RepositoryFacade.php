<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Script;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Script\Execution\Awareness\HookAwareService;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Uuid\Uuid;

class RepositoryFacade extends HookAwareService
{
    private DefinitionInstanceRegistry $registry;

    private Connection $connection;

    private RequestCriteriaBuilder $criteriaBuilder;

    private AclCriteriaValidator $criteriaValidator;

    /**
     * @var array<string, AdminApiSource>
     */
    private array $appSources = [];

    private Context $context;

    public function __construct(
        DefinitionInstanceRegistry $registry,
        Connection $connection,
        RequestCriteriaBuilder $criteriaBuilder,
        AclCriteriaValidator $criteriaValidator
    ) {
        $this->registry = $registry;
        $this->connection = $connection;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->criteriaValidator = $criteriaValidator;
    }

    public function getName(): string
    {
        return 'repository';
    }

    public function inject(Hook $hook, Script $script): void
    {
        $context = $hook->getContext();

        if (!$script->getAppId()) {
            $this->context = $context;

            return;
        }

        $this->context = new Context(
            $this->getAppContextSource($script->getAppId()),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $context->getCurrencyFactor(),
            $context->considerInheritance(),
            $context->getTaxState(),
            $context->getRounding()
        );
    }

    public function search(string $entityName, array $criteria): EntitySearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getRepository($entityName);

        return $repository->search($criteriaObject, $this->context);
    }

    public function ids(string $entityName, array $criteria): IdSearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getRepository($entityName);

        return $repository->searchIds($criteriaObject, $this->context);
    }

    public function aggregate(string $entityName, array $criteria): AggregationResultCollection
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getRepository($entityName);

        return $repository->aggregate($criteriaObject, $this->context);
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

    private function prepareCriteria(string $entityName, array $criteria): Criteria
    {
        $definition = $this->registry->getByEntityName($entityName);
        $criteriaObject = new Criteria();

        $this->criteriaBuilder->fromArray($criteria, $criteriaObject, $definition, $this->context);

        $missingPermissions = $this->criteriaValidator->validate($entityName, $criteriaObject, $this->context);

        if (!empty($missingPermissions)) {
            throw new MissingPrivilegeException($missingPermissions);
        }

        return $criteriaObject;
    }
}
