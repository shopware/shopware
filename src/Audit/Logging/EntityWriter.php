<?php declare(strict_types=1);

namespace Shopware\Audit\Logging;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Audit\Definition\AuditLogDefinition;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Rest\ApiContext;
use Shopware\Api\User\Definition\UserDefinition;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class EntityWriter implements EntityWriterInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $decorated;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var EntitySearcherInterface
     */
    private $searcher;

    /**
     * @var array
     */
    private $mapping = [];

    public function __construct(
        EntityWriterInterface $decorated,
        TokenStorageInterface $tokenStorage,
        EntitySearcherInterface $searcher
    ) {
        $this->decorated = $decorated;
        $this->tokenStorage = $tokenStorage;
        $this->searcher = $searcher;
    }

    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->writeAuditLog($definition, $rawData, $writeContext, __FUNCTION__);

        return $this->decorated->upsert($definition, $rawData, $writeContext);
    }

    public function insert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        /** @var EntityDefinition $definition */
        $primary = $definition::getPrimaryKeys();

        if ($primary->count() === 1 && $primary->first() instanceof UuidField) {
            foreach ($rawData as &$data) {
                if (!isset($data['uuid'])) {
                    $data['uuid'] = Uuid::uuid4()->toString();
                }
            }
        }

        $this->writeAuditLog($definition, $rawData, $writeContext, __FUNCTION__);

        return $this->decorated->insert($definition, $rawData, $writeContext);
    }

    public function update(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->writeAuditLog($definition, $rawData, $writeContext, __FUNCTION__);

        return $this->decorated->update($definition, $rawData, $writeContext);
    }

    private function writeAuditLog(string $definition, array $rawData, WriteContext $writeContext, string $action)
    {
        foreach ($rawData as $data) {
            $log = [
                'entity' => $definition,
                'createdAt' => new \DateTime(),
                'payload' => json_encode($data),
                'userUuid' => $this->getUserUuid($writeContext->getTranslationContext()),
                'action' => $action,
            ];

            if (isset($data['uuid'])) {
                $log['foreignKey'] = $data['uuid'];
            }

            $this->decorated->insert(AuditLogDefinition::class, [$log], $writeContext);
        }
    }

    private function getUserUuid(TranslationContext $context): string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return ApiContext::KERNEL_USER;
        }

        /** @var UserInterface $user */
        $user = $token->getUser();

        $name = $user->getUsername();
        if (array_key_exists($name, $this->mapping)) {
            return $this->mapping[$name];
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new TermQuery(UserDefinition::getEntityName() . '.username', $name));

        $users = $this->searcher->search(UserDefinition::class, $criteria, $context);
        $uuids = $users->getUuids();

        $uuid = array_shift($uuids);

        if (!$uuid) {
            return $this->mapping[$name] = ApiContext::KERNEL_USER;
        }

        return $this->mapping[$name] = $uuid;
    }
}
