<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Aggregate\AppContentElementType\AppContentElementTypeCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ElementTypePersister implements PersisterInterface
{
    private const TYPES_DIRECTORY = 'Resources/content-system/types';

    /**
     * @param EntityRepository<AppContentElementTypeCollection> $contentElementTypeRepository
     */
    public function __construct(
        private readonly EntityRepository $contentElementTypeRepository,
        private readonly ElementTypeSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly ContentElementTypeRegistry $registry,
        private readonly Connection $connection,
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        $appId = $context->app->getId();

        if (!$context->appFilesystem->has(self::TYPES_DIRECTORY)) {
            return;
        }

        $typesDirectory = $context->appFilesystem->path(self::TYPES_DIRECTORY);
        $files = glob($typesDirectory . '/*.yaml');

        if ($files === false || $files === []) {
            return;
        }

        $existing = $this->getExistingTypes($appId, $context->context);

        $upserts = [];
        $processedNames = [];

        foreach ($files as $file) {
            $data = Yaml::parseFile($file);

            if (!\is_array($data)) {
                continue;
            }

            $dto = $this->serializer->denormalize($data);

            $violations = $this->validator->validate($dto);
            if ($violations->count() > 0) {
                throw ContentSystemException::elementTypeInvalid( // @phpstan-ignore shopware.domainException (validation error from content system is appropriate here)
                    $dto->name ?: '<unknown>',
                    $violations
                );
            }

            $dto->toContentElementTypeSpecification();

            $name = $dto->name;
            $normalized = $this->serializer->normalize($dto);
            $hash = Hasher::hash(json_encode($normalized, \JSON_THROW_ON_ERROR));

            $this->checkCollision($name, $appId);

            $processedNames[$name] = true;

            $existingEntity = $existing->filterByProperty('name', $name)->first();

            if ($existingEntity !== null && $existingEntity->getHash() === $hash) {
                continue;
            }

            $payload = [
                'name' => $name,
                'schema' => $normalized,
                'hash' => $hash,
                'active' => true,
                'appId' => $appId,
            ];

            if ($existingEntity !== null) {
                $payload['id'] = $existingEntity->getId();
            } else {
                $payload['id'] = Uuid::randomHex();
            }

            $upserts[] = $payload;
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
    }

    private function getExistingTypes(string $appId, Context $context): AppContentElementTypeCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->contentElementTypeRepository->search($criteria, $context)->getEntities();
    }

    private function checkCollision(string $name, string $appId): void
    {
        if ($this->registry->has($name)) {
            throw AppException::elementTypeCollision($name, 'core/plugin', 'app');
        }

        $existingAppName = $this->connection->fetchOne(
            'SELECT a.name FROM app_content_element_type t
             INNER JOIN app a ON t.app_id = a.id
             WHERE t.name = :name AND t.app_id != :appId',
            ['name' => $name, 'appId' => Uuid::fromHexToBytes($appId)]
        );

        if ($existingAppName !== false) {
            throw AppException::elementTypeCollision($name, $existingAppName, 'app');
        }
    }
}
