<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ResolvedElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\ElementTypeCollisionDetector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Sole write path for app element types into the database. Called during app
 * install, update, and uninstall. DatabaseTypeLoader is the read-side counterpart.
 *
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
        private readonly YamlTypeLoader $loader,
        private readonly ElementTypeCollisionDetector $collisionDetector,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly ElementTypeSpecificationSerializer $serializer,
    ) {
    }

    /**
     * Syncs app element types to DB: validates against registry + inactive types,
     * then upserts changed / deletes removed types. Invalidates the registry cache
     * only when changes were written.
     */
    public function persist(AppLifecycleContext $context): void
    {
        $appId = $context->app->getId();

        $resolvedDtos = $this->loadDtos($context);
        $existing = $this->getExistingTypes($appId, $context->context);

        if ($resolvedDtos === [] && $existing->count() === 0) {
            return;
        }

        if ($resolvedDtos !== []) {
            $proposedNames = $this->buildProposedNames($resolvedDtos);
            $inactiveNames = $this->loadInactiveAppTypeNames($appId, $context->context);

            // Exclude own source to prevent self-collision when updating existing types
            $this->collisionDetector->validate(
                $proposedNames,
                'app:' . $context->app->getName(),
                $inactiveNames,
            );
        }

        $upserts = $this->buildUpserts($resolvedDtos, $existing, $context);
        $deleteIds = $this->buildDeletes($resolvedDtos, $existing);

        if ($upserts !== []) {
            $this->contentElementTypeRepository->upsert($upserts, $context->context);
        }

        if ($deleteIds !== []) {
            $this->contentElementTypeRepository->delete($deleteIds, $context->context);
        }

        if ($upserts !== [] || $deleteIds !== []) {
            $this->registry->invalidate();
        }
    }

    /**
     * Wraps ContentSystemException into AppException to match the app lifecycle's error boundary.
     *
     * @return list<ResolvedElementTypeSpecificationDto>
     */
    private function loadDtos(AppLifecycleContext $context): array
    {
        $typesDir = $context->appFilesystem->path(self::TYPES_DIRECTORY);

        try {
            return $this->loader->loadDtosFromDirectory(
                $typesDir,
                'app:' . $context->app->getName(),
                $context->app->getName(),
            );
        } catch (ContentSystemException $e) {
            throw AppException::contentSystemElementTypeLoadFailed(self::TYPES_DIRECTORY, $e->getMessage(), $e);
        }
    }

    private function getExistingTypes(string $appId, Context $context): AppContentSystemElementTypeCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->contentElementTypeRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param list<ResolvedElementTypeSpecificationDto> $resolvedDtos
     *
     * @return array<string, string>
     */
    private function buildProposedNames(array $resolvedDtos): array
    {
        $names = [];

        foreach ($resolvedDtos as $dto) {
            $names[$dto->name] = $dto->source;
        }

        return $names;
    }

    /**
     * @return array<string, string> name => 'app:<AppName>' source label
     */
    private function loadInactiveAppTypeNames(string $excludeAppId, Context $context): array
    {
        $criteria = new Criteria();
        // Inactive types from other apps still occupy name space; exclude the current app's own deactivated types
        $criteria->addFilter(new EqualsFilter('active', false));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('appId', $excludeAppId)]));
        $criteria->addAssociation('app');

        /** @var AppContentSystemElementTypeCollection $entities */
        $entities = $this->contentElementTypeRepository->search($criteria, $context)->getEntities();

        $names = [];

        foreach ($entities as $entity) {
            $app = $entity->getApp();
            if ($app === null) {
                continue;
            }

            $names[$entity->getName()] = 'app:' . $app->getName();
        }

        return $names;
    }

    private function computeHash(ElementTypeSpecificationDto $dto): string
    {
        return Hasher::hash(json_encode($this->serializer->normalize($dto), \JSON_THROW_ON_ERROR));
    }

    /**
     * Skips unchanged types (hash match) to avoid unnecessary writes on repeated installs/updates.
     *
     * @param list<ResolvedElementTypeSpecificationDto> $resolvedDtos
     *
     * @return list<array<string, mixed>>
     */
    private function buildUpserts(
        array $resolvedDtos,
        AppContentSystemElementTypeCollection $existing,
        AppLifecycleContext $context,
    ): array {
        $upserts = [];

        foreach ($resolvedDtos as $resolvedDto) {
            $hash = $this->computeHash($resolvedDto->dto);
            $existingEntity = $existing->filterByProperty('name', $resolvedDto->name)->first();

            if ($existingEntity !== null && $existingEntity->getHash() === $hash) {
                continue;
            }

            $upserts[] = [
                'id' => $existingEntity?->getId() ?? Uuid::randomHex(),
                'name' => $resolvedDto->name,
                'schema' => $this->serializer->normalize($resolvedDto->dto),
                'hash' => $hash,
                'active' => $context->app->isActive(),
                'appId' => $context->app->getId(),
            ];
        }

        return $upserts;
    }

    /**
     * @param list<ResolvedElementTypeSpecificationDto> $resolvedDtos
     *
     * @return list<array{id: string}>
     */
    private function buildDeletes(array $resolvedDtos, AppContentSystemElementTypeCollection $existing): array
    {
        $processedNames = [];
        foreach ($resolvedDtos as $dto) {
            $processedNames[$dto->name] = true;
        }

        $deleteIds = [];
        foreach ($existing as $existingEntity) {
            if (!isset($processedNames[$existingEntity->getName()])) {
                $deleteIds[] = ['id' => $existingEntity->getId()];
            }
        }

        return $deleteIds;
    }
}
