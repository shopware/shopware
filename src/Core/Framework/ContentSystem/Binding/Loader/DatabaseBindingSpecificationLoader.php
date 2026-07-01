<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\Binding\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Loads active app bindings from the database in prod; returns empty in dev, where apps are loaded
 * from the filesystem by YamlBindingSpecificationLoader.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DatabaseBindingSpecificationLoader extends AbstractContentSystemBindingSpecificationLoader
{
    public function __construct(
        private readonly BindingSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly Connection $connection,
        private readonly string $environment,
    ) {
    }

    /**
     * @return list<BindingSpecification>
     */
    public function load(): array
    {
        // In dev, app bindings are loaded from filesystem by YamlBindingSpecificationLoader via the compiler pass
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
            $name = $row['name'] ?: '<unknown>';

            try {
                $schema = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ContentSystemException::bindingSpecificationLoadFailed($name, $e->getMessage(), $e);
            }

            if (!\is_array($schema)) {
                throw ContentSystemException::bindingSpecificationLoadFailed($name, 'persisted schema must decode to an array/map, got ' . get_debug_type($schema));
            }

            $resolved[] = new ResolvedBindingSpecificationDto(
                $name,
                'app:' . $row['app_name'],
                $this->serializer->denormalize($schema),
            );
        }

        $dtos = [];
        foreach ($resolved as $resolvedDto) {
            $dtos[$resolvedDto->id] = $resolvedDto->dto;
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
