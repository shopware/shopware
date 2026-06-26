<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemStyleOption\AppContentSystemStyleOptionCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\ResolvedStyleOptionSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionCollisionDetector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Sole write path for app style options into the database. Called during app install and update.
 * DatabaseStyleOptionLoader is the read-side counterpart.
 *
 * @internal
 */
#[Package('framework')]
class ContentSystemStyleOptionPersister
{
    public const STYLE_OPTIONS_DIRECTORY = 'Resources/content-system/style-options';

    /**
     * @param EntityRepository<AppContentSystemStyleOptionCollection> $styleOptionRepository
     */
    public function __construct(
        private readonly EntityRepository $styleOptionRepository,
        private readonly YamlStyleOptionLoader $loader,
        private readonly StyleOptionCollisionDetector $collisionDetector,
        private readonly AbstractContentSystemStyleOptionRegistry $registry,
        private readonly StyleOptionSpecificationSerializer $serializer,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Syncs app style options to DB: validates against registry + inactive options, then upserts
     * changed / deletes removed options. Invalidates the registry cache only when changes were written.
     */
    public function persist(AppPersistContext $context): void
    {
        $appId = $context->app->getId();

        $resolvedDtos = $this->loadDtos($context);
        $existing = $this->getExistingOptions($appId, $context->context);

        if ($resolvedDtos === [] && $existing->count() === 0) {
            return;
        }

        if ($resolvedDtos !== []) {
            $proposedNames = $this->buildProposedNames($resolvedDtos);
            $inactiveNames = $this->loadInactiveAppOptionNames($appId, $context->context);

            // Exclude own source to prevent self-collision when updating existing options
            $this->collisionDetector->validate(
                $proposedNames,
                'app:' . $context->app->getName(),
                $inactiveNames,
            );
        }

        $upserts = $this->buildUpserts($resolvedDtos, $existing, $context);
        $deleteIds = $this->buildDeletes($resolvedDtos, $existing);

        // Upsert and delete are one atomic unit: a partial failure must not leave the registered set
        // half-synced (a stale-but-valid row alongside a committed new one).
        $this->connection->transactional(function () use ($upserts, $deleteIds, $context): void {
            if ($upserts !== []) {
                try {
                    $this->styleOptionRepository->upsert($upserts, $context->context);
                } catch (UniqueConstraintViolationException $e) {
                    throw AppException::contentSystemStyleOptionDuplicate(
                        array_column($upserts, 'name'),
                        'app:' . $context->app->getName(),
                        $e,
                    );
                }
            }

            if ($deleteIds !== []) {
                $this->styleOptionRepository->delete($deleteIds, $context->context);
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
     * @return list<ResolvedStyleOptionSpecificationDto>
     */
    private function loadDtos(AppPersistContext $context): array
    {
        $directory = $context->appFilesystem->path(self::STYLE_OPTIONS_DIRECTORY);

        try {
            return $this->loader->loadDtosFromDirectory(
                $directory,
                'app:' . $context->app->getName(),
            );
        } catch (ContentSystemException $e) {
            throw AppException::contentSystemStyleOptionLoadFailed(self::STYLE_OPTIONS_DIRECTORY, $e->getMessage(), $e);
        }
    }

    private function getExistingOptions(string $appId, Context $context): AppContentSystemStyleOptionCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->styleOptionRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param list<ResolvedStyleOptionSpecificationDto> $resolvedDtos
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
    private function loadInactiveAppOptionNames(string $excludeAppId, Context $context): array
    {
        $criteria = new Criteria();
        // Options of inactive apps still occupy name space; exclude the current app's own options
        $criteria->addFilter(new EqualsFilter('app.active', false));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('appId', $excludeAppId)]));
        $criteria->addAssociation('app');

        $entities = $this->styleOptionRepository->search($criteria, $context)->getEntities();

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

    /**
     * Skips unchanged options (hash match) to avoid unnecessary writes on repeated installs/updates.
     *
     * @param list<ResolvedStyleOptionSpecificationDto> $resolvedDtos
     *
     * @return list<array<string, mixed>>
     */
    private function buildUpserts(
        array $resolvedDtos,
        AppContentSystemStyleOptionCollection $existing,
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
            $existingEntity = $existingByName[$resolvedDto->name] ?? null;

            if ($existingEntity !== null && $existingEntity->getHash() === $hash) {
                continue;
            }

            $upserts[] = [
                'id' => $existingEntity?->getId() ?? Uuid::randomHex(),
                'name' => $resolvedDto->name,
                'schema' => $normalized,
                'hash' => $hash,
                'appId' => $context->app->getId(),
            ];
        }

        return $upserts;
    }

    /**
     * @param list<ResolvedStyleOptionSpecificationDto> $resolvedDtos
     *
     * @return list<array{id: string}>
     */
    private function buildDeletes(array $resolvedDtos, AppContentSystemStyleOptionCollection $existing): array
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
