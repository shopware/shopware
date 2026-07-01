<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\ResolvedBindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Sole write path for app bindings into the database. Called during app install and update.
 * DatabaseBindingSpecificationLoader is the read-side counterpart.
 *
 * Bindings are unique only within their app (DB unique key on app_id+name), unlike style options
 * which are globally unique — so, unlike ContentSystemStyleOptionPersister, this persister does not
 * run a cross-source collision check: within-app duplicate ids are already caught by
 * loadDtosFromDirectory(), and the same id from a different app is legitimate.
 *
 * @internal
 */
#[Package('framework')]
class ContentSystemBindingSpecificationPersister
{
    public const DIRECTORY = 'Resources/content-system/binding-specifications';

    /**
     * @param EntityRepository<AppContentSystemBindingSpecificationCollection> $bindingSpecificationRepository
     */
    public function __construct(
        private readonly EntityRepository $bindingSpecificationRepository,
        private readonly YamlBindingSpecificationLoader $loader,
        private readonly AbstractContentSystemBindingSpecificationRegistry $registry,
        private readonly BindingSpecificationSerializer $serializer,
        private readonly Connection $connection,
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
        // that rolled back.
        if ($upserts !== [] || $deleteIds !== []) {
            $this->registry->invalidate();
        }
    }

    /**
     * Wraps ContentSystemException into AppException to match the app lifecycle's error boundary.
     *
     * @return list<ResolvedBindingSpecificationDto>
     */
    private function loadDtos(AppPersistContext $context): array
    {
        $directory = $context->appFilesystem->path(self::DIRECTORY);

        try {
            return $this->loader->loadDtosFromDirectory(
                $directory,
                'app:' . $context->app->getName(),
                $context->app->getName(),
            );
        } catch (ContentSystemException $e) {
            throw AppException::contentSystemBindingSpecificationLoadFailed(self::DIRECTORY, $e->getMessage(), $e);
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
