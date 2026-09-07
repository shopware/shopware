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
 * A persisted row is runtime data that can drift after install (a dependency deactivated, a column
 * hand-edited): a row whose schema fails to decode or validate aborts the whole load, like
 * YamlStyleOptionLoader, which fails hard on an authored file.
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
