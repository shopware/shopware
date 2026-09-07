<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDtoCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A persisted row is runtime data that can drift after install (a dependency deactivated, a column
 * hand-edited): a row whose schema fails to decode or validate aborts the whole load, like
 * YamlTypeLoader, which fails hard on an authored file.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DatabaseTypeLoader extends AbstractContentSystemElementTypeLoader
{
    public function __construct(
        private readonly ElementTypeSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly Connection $connection,
        private readonly string $environment,
    ) {
    }

    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    public function load(): array
    {
        if ($this->environment === 'dev') {
            return [];
        }

        /** @var list<array{name: string, schema: string, app_name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.name, t.schema, a.name as app_name
             FROM app_content_system_element_type t
             INNER JOIN app a ON t.app_id = a.id
             WHERE a.active = 1'
        );

        $resolvedSpecificationDtos = [];

        foreach ($rows as $row) {
            $source = 'app:' . $row['app_name'];
            $name = $row['name'];
            $identifier = $source . ':' . ($name === '' ? '<unknown>' : $name);

            if ($name === '') {
                throw ContentSystemException::elementTypeLoadFailed($identifier, 'persisted row has no name and cannot be registered');
            }

            try {
                $schema = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ContentSystemException::elementTypeLoadFailed($identifier, 'Invalid JSON schema: ' . $e->getMessage(), $e);
            }

            if (!\is_array($schema)) {
                throw ContentSystemException::elementTypeLoadFailed($identifier, 'Persisted schema must decode to an array/map, got ' . get_debug_type($schema));
            }

            try {
                $dto = $this->serializer->denormalize($schema);
            } catch (\Throwable $e) {
                throw ContentSystemException::elementTypeLoadFailed($identifier, 'Invalid schema: ' . $e->getMessage(), $e);
            }

            $resolvedSpecificationDtos[] = new ResolvedElementTypeSpecificationDto($name, $source, $dto);
        }

        $dtos = [];
        foreach ($resolvedSpecificationDtos as $resolvedSpecificationDto) {
            $dtos[$resolvedSpecificationDto->name] = $resolvedSpecificationDto->dto;
        }

        $violations = $this->validator->validate(new ElementTypeSpecificationDtoCollection($dtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::elementTypesInvalid($violations);
        }

        return array_map(
            static fn (ResolvedElementTypeSpecificationDto $resolvedSpecificationDto) => $resolvedSpecificationDto->toSpecification(),
            $resolvedSpecificationDtos,
        );
    }
}
