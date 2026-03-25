<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemElementTypePersister implements PersisterInterface
{
    private const TYPES_DIRECTORY = 'Resources/content-system/types';

    /**
     * @param EntityRepository<AppContentSystemElementTypeCollection> $contentElementTypeRepository
     */
    public function __construct(
        private readonly EntityRepository $contentElementTypeRepository,
        private readonly ElementTypeSpecificationSerializer $serializer,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly Connection $connection,
        private readonly YamlTypeLoader $loader,
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        $appId = $context->app->getId();
        $typesDir = $context->appFilesystem->path(self::TYPES_DIRECTORY);

        try {
            $resolvedDtos = $this->loader->loadDtosFromDirectory(
                $typesDir,
                'app:' . $context->app->getName(),
                $context->app->getName(),
            );
        } catch (ContentSystemException $e) {
            throw AppException::contentSystemElementTypeLoadFailed(self::TYPES_DIRECTORY, $e->getMessage(), $e);
        }

        if ($resolvedDtos === []) {
            return;
        }

        $existing = $this->getExistingTypes($appId, $context->context);
        $upserts = [];
        $processedNames = [];

        foreach ($resolvedDtos as $resolvedDto) {
            $normalized = $this->serializer->normalize($resolvedDto->dto);
            $hash = Hasher::hash(json_encode($normalized, \JSON_THROW_ON_ERROR));

            $this->checkCollision($resolvedDto->name, $appId);
            $processedNames[$resolvedDto->name] = true;

            $existingEntity = $existing->filterByProperty('name', $resolvedDto->name)->first();

            if ($existingEntity !== null && $existingEntity->getHash() === $hash) {
                continue;
            }

            $upserts[] = [
                'id' => $existingEntity?->getId() ?? Uuid::randomHex(),
                'name' => $resolvedDto->name,
                'schema' => $normalized,
                'hash' => $hash,
                'active' => $context->app->isActive(),
                'appId' => $appId,
            ];
        }

        if ($upserts !== []) {
            $this->contentElementTypeRepository->upsert($upserts, $context->context);
        }

        $deleteIds = [];
        foreach ($existing as $existingEntity) {
            if (!isset($processedNames[$existingEntity->getName()])) {
                $deleteIds[] = ['id' => $existingEntity->getId()];
            }
        }

        if ($deleteIds !== []) {
            $this->contentElementTypeRepository->delete($deleteIds, $context->context);
        }

        if ($upserts !== [] || $deleteIds !== []) {
            $this->registry->invalidate();
        }
    }

    private function getExistingTypes(string $appId, Context $context): AppContentSystemElementTypeCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->contentElementTypeRepository->search($criteria, $context)->getEntities();
    }

    private function checkCollision(string $name, string $appId): void
    {
        if ($this->registry->has($name)) {
            throw AppException::contentSystemElementTypeCollision($name, 'core/plugin', 'app');
        }

        $existingAppName = $this->connection->fetchOne(
            'SELECT a.name FROM app_content_system_element_type t
             INNER JOIN app a ON t.app_id = a.id
             WHERE t.name = :name AND t.app_id != :appId',
            ['name' => $name, 'appId' => Uuid::fromHexToBytes($appId)]
        );

        if ($existingAppName !== false) {
            throw AppException::contentSystemElementTypeCollision($name, $existingAppName, 'app');
        }
    }
}
