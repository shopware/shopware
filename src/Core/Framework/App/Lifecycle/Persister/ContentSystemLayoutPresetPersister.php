<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset\AppContentSystemLayoutPresetCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
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
class ContentSystemLayoutPresetPersister
{
    private const PRESETS_DIRECTORY = 'Resources/content-system/presets';

    /**
     * @param EntityRepository<AppContentSystemLayoutPresetCollection> $presetRepository
     */
    public function __construct(
        private readonly EntityRepository $presetRepository,
        private readonly YamlLayoutPresetLoader $loader,
        private readonly AbstractContentSystemLayoutPresetRegistry $registry,
    ) {
    }

    public function persist(AppPersistContext $context): void
    {
        $appId = $context->app->getId();

        $raw = $this->loadRaw($context);
        $existing = $this->getExistingPresets($appId, $context->context);

        if ($raw === [] && $existing->count() === 0) {
            return;
        }

        $upserts = $this->buildUpserts($raw, $existing, $appId);
        $deleteIds = $this->buildDeletes($raw, $existing);

        if ($upserts !== []) {
            try {
                $this->presetRepository->upsert($upserts, $context->context);
            } catch (UniqueConstraintViolationException $e) {
                throw AppException::contentSystemLayoutPresetDuplicate(
                    array_keys($raw),
                    'app:' . $context->app->getName(),
                    $e,
                );
            }
        }

        if ($deleteIds !== []) {
            $this->presetRepository->delete($deleteIds, $context->context);
        }

        if ($upserts !== [] || $deleteIds !== []) {
            $this->registry->invalidate();
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadRaw(AppPersistContext $context): array
    {
        $presetsDir = $context->appFilesystem->path(self::PRESETS_DIRECTORY);

        try {
            return $this->loader->readRawFromDirectory($presetsDir, 'app:' . $context->app->getName(), $context->app->getName());
        } catch (ContentSystemException $e) {
            throw AppException::contentSystemLayoutPresetLoadFailed(self::PRESETS_DIRECTORY, $e->getMessage(), $e);
        }
    }

    private function getExistingPresets(string $appId, Context $context): AppContentSystemLayoutPresetCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->presetRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param array<string, array<string, mixed>> $raw
     *
     * @return list<array<string, mixed>>
     */
    private function buildUpserts(array $raw, AppContentSystemLayoutPresetCollection $existing, string $appId): array
    {
        $existingByName = [];
        foreach ($existing as $entity) {
            $existingByName[$entity->getName()] = $entity;
        }

        $upserts = [];

        foreach ($raw as $name => $data) {
            $hash = Hasher::hash(json_encode($data, \JSON_THROW_ON_ERROR));
            $existingEntity = $existingByName[$name] ?? null;

            if ($existingEntity !== null && $existingEntity->getHash() === $hash) {
                continue;
            }

            $upserts[] = [
                'id' => $existingEntity?->getId() ?? Uuid::randomHex(),
                'name' => $name,
                'schema' => $data,
                'hash' => $hash,
                'appId' => $appId,
            ];
        }

        return $upserts;
    }

    /**
     * @param array<string, array<string, mixed>> $raw
     *
     * @return list<array{id: string}>
     */
    private function buildDeletes(array $raw, AppContentSystemLayoutPresetCollection $existing): array
    {
        $deleteIds = [];

        foreach ($existing as $existingEntity) {
            if (!\array_key_exists($existingEntity->getName(), $raw)) {
                $deleteIds[] = ['id' => $existingEntity->getId()];
            }
        }

        return $deleteIds;
    }
}
