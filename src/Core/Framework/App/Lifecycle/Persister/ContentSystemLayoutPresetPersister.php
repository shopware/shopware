<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset\AppContentSystemLayoutPresetCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
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
        private readonly LayoutPresetSpecificationSerializer $serializer,
        private readonly AbstractContentSystemLayoutPresetRegistry $registry,
    ) {
    }

    public function persist(AppPersistContext $context): void
    {
        $appId = $context->app->getId();

        $dtos = $this->loadDtos($context);
        $existing = $this->getExistingPresets($appId, $context->context);

        if ($dtos === [] && $existing->count() === 0) {
            return;
        }

        $upserts = $this->buildUpserts($dtos, $existing, $appId);
        $deleteIds = $this->buildDeletes($dtos, $existing);

        if ($upserts !== []) {
            try {
                $this->presetRepository->upsert($upserts, $context->context);
            } catch (UniqueConstraintViolationException $e) {
                throw AppException::contentSystemLayoutPresetDuplicate(
                    array_keys($dtos),
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
     * @return array<string, LayoutPresetSpecificationDto>
     */
    private function loadDtos(AppPersistContext $context): array
    {
        $presetsDir = $context->appFilesystem->path(self::PRESETS_DIRECTORY);

        try {
            return $this->loader->loadDtosFromDirectory($presetsDir, 'app:' . $context->app->getName(), $context->app->getName());
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
     * @param array<string, LayoutPresetSpecificationDto> $dtos
     *
     * @return list<array<string, mixed>>
     */
    private function buildUpserts(array $dtos, AppContentSystemLayoutPresetCollection $existing, string $appId): array
    {
        $existingByName = [];
        foreach ($existing as $entity) {
            $existingByName[$entity->getName()] = $entity;
        }

        $upserts = [];

        foreach ($dtos as $name => $dto) {
            $schema = $this->serializer->normalize($dto);
            $hash = Hasher::hash(json_encode($schema, \JSON_THROW_ON_ERROR));
            $existingEntity = $existingByName[$name] ?? null;

            if ($existingEntity !== null && $existingEntity->getHash() === $hash) {
                continue;
            }

            $upserts[] = [
                'id' => $existingEntity?->getId() ?? Uuid::randomHex(),
                'name' => $name,
                'schema' => $schema,
                'hash' => $hash,
                'appId' => $appId,
            ];
        }

        return $upserts;
    }

    /**
     * @param array<string, LayoutPresetSpecificationDto> $dtos
     *
     * @return list<array{id: string}>
     */
    private function buildDeletes(array $dtos, AppContentSystemLayoutPresetCollection $existing): array
    {
        $deleteIds = [];

        foreach ($existing as $existingEntity) {
            if (!\array_key_exists($existingEntity->getName(), $dtos)) {
                $deleteIds[] = ['id' => $existingEntity->getId()];
            }
        }

        return $deleteIds;
    }
}
