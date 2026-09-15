<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Persisted active-app rows follow the same fail-fast contract as definitions loaded by
 * {@see YamlBindingSpecificationLoader}.
 * Every row must have a name, and its schema must decode to a map, deserialize, and validate
 * successfully; otherwise, the whole load aborts.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DatabaseBindingSpecificationLoader extends AbstractContentSystemBindingSpecificationLoader
{
    public function __construct(
        private readonly string $environment,
        private readonly Connection $connection,
        private readonly BindingSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return list<BindingSpecification>
     */
    public function load(): array
    {
        if ($this->environment === 'dev') {
            return [];
        }

        /** @var list<array{name: string, schema: string, app_name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT b.name, b.`schema`, a.name as app_name
             FROM app_content_system_binding_specification b
             INNER JOIN app a ON b.app_id = a.id
             WHERE a.active = 1'
        );

        $resolved = [];

        foreach ($rows as $row) {
            $source = 'app:' . $row['app_name'];

            if ($row['name'] === '') {
                throw ContentSystemException::bindingSpecificationLoadFailed($source . ':<unknown>', 'persisted row has no name and cannot be registered');
            }

            $name = $row['name'];
            $identifier = $source . ':' . $name;

            try {
                $schema = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ContentSystemException::bindingSpecificationLoadFailed($identifier, 'Invalid JSON schema: ' . $e->getMessage(), $e);
            }

            if (!\is_array($schema)) {
                throw ContentSystemException::bindingSpecificationLoadFailed($identifier, 'Persisted schema must decode to an array/map, got ' . get_debug_type($schema));
            }

            try {
                $dto = $this->serializer->denormalize($schema);
            } catch (\Throwable $e) {
                throw ContentSystemException::bindingSpecificationLoadFailed($identifier, 'Invalid schema: ' . $e->getMessage(), $e);
            }

            $resolved[] = new ResolvedBindingSpecificationDto($name, $source, $dto);
        }

        $dtos = [];
        foreach ($resolved as $resolvedDto) {
            // Binding names are unique only within an app, so the source-qualified id keeps equal bare names
            // from different apps distinct in the collection and ensures that all rows are validated.
            $dtos[$resolvedDto->source . ':' . $resolvedDto->id] = $resolvedDto->dto;
        }

        $violations = $this->validator->validate(new BindingSpecificationDtoCollection($dtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::bindingSpecificationsInvalid($violations);
        }

        return array_map(
            static fn (ResolvedBindingSpecificationDto $resolvedDto) => $resolvedDto->toSpecification(),
            $resolved,
        );
    }
}
