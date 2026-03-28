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
 * Loads active app element types from the database. Only operates in prod — in dev,
 * app types are loaded from the filesystem by YamlTypeLoader instead. Core, bundle,
 * and plugin types always go through YamlTypeLoader regardless of environment.
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
        // In dev, app types are loaded from filesystem by YamlTypeLoader via the compiler pass
        if ($this->environment === 'dev') {
            return [];
        }

        /** @var list<array{name: string, schema: string, app_name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.name, t.schema, a.name as app_name
             FROM app_content_system_element_type t
             INNER JOIN app a ON t.app_id = a.id
             WHERE t.active = 1'
        );

        $resolvedSpecificationDtos = [];

        foreach ($rows as $row) {
            $name = $row['name'] ?: '<unknown>';

            try {
                $schema = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ContentSystemException::elementTypeLoadFailed($name, $e->getMessage(), $e);
            }

            $dto = $this->serializer->denormalize($schema);

            $resolvedSpecificationDtos[] = new ResolvedElementTypeSpecificationDto(
                $name,
                'app:' . $row['app_name'],
                $dto,
            );
        }

        $specificationDtos = [];
        foreach ($resolvedSpecificationDtos as $resolvedSpecificationDto) {
            $specificationDtos[$resolvedSpecificationDto->name] = $resolvedSpecificationDto->dto;
        }

        $violations = $this->validator->validate(new ElementTypeSpecificationDtoCollection($specificationDtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::elementTypesInvalid($violations);
        }

        return array_map(
            static fn (ResolvedElementTypeSpecificationDto $resolvedSpecificationDto) => $resolvedSpecificationDto->toSpecification(),
            $resolvedSpecificationDtos,
        );
    }
}
