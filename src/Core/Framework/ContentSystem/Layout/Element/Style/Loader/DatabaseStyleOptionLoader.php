<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Persisted active-app rows follow the same fail-fast contract as definitions loaded by
 * {@see YamlStyleOptionLoader}.
 * Every row must have a name, and its schema must decode to a map, deserialize, and validate
 * successfully; otherwise, the whole load aborts.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DatabaseStyleOptionLoader extends AbstractContentSystemStyleOptionLoader
{
    public function __construct(
        private readonly StyleOptionSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly Connection $connection,
        private readonly string $environment,
    ) {
    }

    /**
     * @return list<StyleOptionSpecification>
     */
    public function load(): array
    {
        if ($this->environment === 'dev') {
            return [];
        }

        /** @var list<array{name: string, schema: string, app_name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.name, o.`schema`, a.name as app_name
             FROM app_content_system_style_option o
             INNER JOIN app a ON o.app_id = a.id
             WHERE a.active = 1'
        );

        $resolved = [];

        foreach ($rows as $row) {
            $source = 'app:' . $row['app_name'];
            $name = $row['name'];
            $identifier = $source . ':' . ($name === '' ? '<unknown>' : $name);

            if ($name === '') {
                throw ContentSystemException::styleOptionLoadFailed($identifier, 'persisted row has no name and cannot be registered');
            }

            try {
                $schema = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ContentSystemException::styleOptionLoadFailed($identifier, 'Invalid JSON schema: ' . $e->getMessage(), $e);
            }

            if (!\is_array($schema)) {
                throw ContentSystemException::styleOptionLoadFailed($identifier, 'Persisted schema must decode to an array/map, got ' . get_debug_type($schema));
            }

            try {
                $dto = $this->serializer->denormalize($schema);
            } catch (\Throwable $e) {
                throw ContentSystemException::styleOptionLoadFailed($identifier, 'Invalid schema: ' . $e->getMessage(), $e);
            }

            $resolved[] = new ResolvedStyleOptionSpecificationDto($name, $source, $dto);
        }

        $dtos = [];
        foreach ($resolved as $resolvedDto) {
            // Style option names are globally unique across persisted app rows, so the bare name keeps every
            // row distinct in the collection and ensures that all rows are validated.
            $dtos[$resolvedDto->name] = $resolvedDto->dto;
        }

        $violations = $this->validator->validate(new StyleOptionSpecificationDtoCollection($dtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::styleOptionsInvalid($violations);
        }

        return array_map(
            static fn (ResolvedStyleOptionSpecificationDto $resolvedDto) => $resolvedDto->toSpecification(),
            $resolved,
        );
    }
}
