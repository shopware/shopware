<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\ResolvedBindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Lock\LockFactory;

/**
 * Sole write path for app bindings into the database.
 * {@see DatabaseBindingSpecificationLoader}
 *
 * @internal
 */
#[Package('framework')]
class ContentSystemBindingSpecificationPersister
{
    /**
     * The app's element-type directory, scanned for inline `bindings:` sections. Own copy of the convention
     * string {@see \Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemElementTypePersister} and
     * {@see \Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemElementTypeCompilerPass}
     * also declare; each consumer owns its copy by convention.
     */
    private const TYPES_DIRECTORY = 'Resources/content-system/types';

    /**
     * @param EntityRepository<AppContentSystemBindingSpecificationCollection> $bindingSpecificationRepository
     */
    public function __construct(
        private readonly YamlBindingSpecificationLoader $loader,
        private readonly YamlTypeLoader $typeLoader,
        private readonly EntityRepository $bindingSpecificationRepository,
        private readonly BindingSpecificationSerializer $serializer,
        private readonly Connection $connection,
        private readonly AbstractContentSystemBindingSpecificationRegistry $registry,
        private readonly LockFactory $lockFactory,
    ) {
    }

    /**
     * Syncs app bindings to DB: upserts changed / deletes removed bindings for this app. Invalidates
     * the registry cache only when changes were written.
     */
    public function persist(AppPersistContext $context): void
    {
        $appId = $context->app->getId();

        $resolvedDtos = $this->loadDtos($context);

        // Serialize concurrent same-app persists: read the existing set, compute the delta, and write it
        // as one lock-held unit, so a racing install cannot diff against a stale snapshot and leave a
        // superset of the intended final state.
        $lock = $this->lockFactory->createLock('content_system_binding_persist_' . $appId, 5.0);
        $lock->acquire(true);

        try {
            $existing = $this->getExistingBindings($appId, $context->context);

            if ($resolvedDtos === [] && $existing->count() === 0) {
                return;
            }

            $upserts = $this->buildUpserts($resolvedDtos, $existing, $context);
            $deleteIds = $this->buildDeletes($resolvedDtos, $existing);

            // Upsert and delete are one atomic unit: a partial failure must not leave the registered set
            // half-synced (a stale-but-valid row alongside a committed new one).
            $this->connection->transactional(function () use ($upserts, $deleteIds, $context): void {
                if ($upserts !== []) {
                    try {
                        $this->bindingSpecificationRepository->upsert($upserts, $context->context);
                    } catch (UniqueConstraintViolationException $e) {
                        throw AppException::contentSystemBindingSpecificationDuplicate(
                            array_column($upserts, 'name'),
                            'app:' . $context->app->getName(),
                            $e,
                        );
                    }
                }

                if ($deleteIds !== []) {
                    $this->bindingSpecificationRepository->delete($deleteIds, $context->context);
                }
            });

            // Invalidate only after the transaction commits, so the cache is never refreshed from a write
            // that rolled back. Kept inside the lock so read → write → invalidate is one critical section.
            if ($upserts !== [] || $deleteIds !== []) {
                $this->registry->invalidate();
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Loads the inline `bindings:` sections of the app's element-type files. Each is canonicalized against a type
     * overlay built from the app's own types, because the app is inactive at install time so its types are not yet
     * in the element-type registry ({@see \Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\DatabaseTypeLoader}
     * and the compiler pass both surface active apps only).
     * Wraps ContentSystemException into AppException to match the app lifecycle's error boundary.
     *
     * @return list<ResolvedBindingSpecificationDto>
     */
    private function loadDtos(AppPersistContext $context): array
    {
        $appName = $context->app->getName();
        $source = 'app:' . $appName;
        $typesDirectory = $context->appFilesystem->path(self::TYPES_DIRECTORY);

        $typeOverlay = $this->buildTypeOverlay($typesDirectory, $source, $appName);

        try {
            return $this->loader->loadDtosFromTypeDirectory($typesDirectory, $source, $appName, $typeOverlay);
        } catch (ContentSystemException $e) {
            throw AppException::contentSystemBindingSpecificationLoadFailed(self::TYPES_DIRECTORY, $e->getMessage(), $e);
        }
    }

    /**
     * The app's own types keyed by resolved type name. Wraps its own ContentSystemException into AppException
     * because it runs before the load's try block; without the wrap a malformed type file would escape the
     * app lifecycle's AppException boundary unwrapped.
     *
     * @return array<string, ContentSystemElementTypeSpecification>
     */
    private function buildTypeOverlay(string $typesDirectory, string $source, string $prefix): array
    {
        try {
            return $this->typeLoader->loadOverlayFromDirectory($typesDirectory, $source, $prefix);
        } catch (ContentSystemException $e) {
            throw AppException::contentSystemBindingSpecificationLoadFailed(self::TYPES_DIRECTORY, $e->getMessage(), $e);
        }
    }

    private function getExistingBindings(string $appId, Context $context): AppContentSystemBindingSpecificationCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->bindingSpecificationRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Skips unchanged bindings (hash match) to avoid unnecessary writes on repeated installs/updates.
     *
     * @param list<ResolvedBindingSpecificationDto> $resolvedDtos
     *
     * @return list<array<string, mixed>>
     */
    private function buildUpserts(
        array $resolvedDtos,
        AppContentSystemBindingSpecificationCollection $existing,
        AppPersistContext $context,
    ): array {
        $existingByName = [];
        foreach ($existing as $entity) {
            $existingByName[$entity->getName()] = $entity;
        }

        $upserts = [];

        foreach ($resolvedDtos as $resolvedDto) {
            $normalized = $this->serializer->normalize($resolvedDto->dto);
            $hash = Hasher::hash(json_encode($normalized, \JSON_THROW_ON_ERROR));
            $existingEntity = $existingByName[$resolvedDto->id] ?? null;

            if ($existingEntity !== null && $existingEntity->getHash() === $hash) {
                continue;
            }

            $upserts[] = [
                'id' => $existingEntity?->getId() ?? Uuid::randomHex(),
                'name' => $resolvedDto->id,
                'schema' => $normalized,
                'hash' => $hash,
                'appId' => $context->app->getId(),
            ];
        }

        return $upserts;
    }

    /**
     * @param list<ResolvedBindingSpecificationDto> $resolvedDtos
     *
     * @return list<array{id: string}>
     */
    private function buildDeletes(array $resolvedDtos, AppContentSystemBindingSpecificationCollection $existing): array
    {
        $processedNames = [];
        foreach ($resolvedDtos as $dto) {
            $processedNames[$dto->id] = true;
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
