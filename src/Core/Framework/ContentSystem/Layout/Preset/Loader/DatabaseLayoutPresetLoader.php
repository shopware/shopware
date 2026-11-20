<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDtoCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DatabaseLayoutPresetLoader extends AbstractContentSystemLayoutPresetLoader
{
    public function __construct(
        private readonly LayoutPresetSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly Connection $connection,
        private readonly string $environment,
    ) {
    }

    /**
     * @return array<string, LayoutPresetSpecificationDto>
     */
    public function load(): array
    {
        if ($this->environment === 'dev') {
            return [];
        }

        /** @var list<array{name: string, schema: string, app_name: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.name, p.schema, a.name as app_name
             FROM app_content_system_layout_preset p
             INNER JOIN app a ON p.app_id = a.id
             WHERE a.active = 1'
        );

        $dtos = [];

        foreach ($rows as $row) {
            $identifier = 'app:' . $row['app_name'] . ':' . ($row['name'] ?: '<unknown>');

            try {
                $data = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ContentSystemException::layoutPresetLoadFailed($identifier, 'Invalid JSON data: ' . $e->getMessage(), $e);
            }

            if (!\is_array($data)) {
                throw ContentSystemException::layoutPresetLoadFailed($identifier, 'Persisted data must decode to an array/map, got ' . get_debug_type($data));
            }

            $dtos[$row['name']] = $this->serializer->denormalize($data);
        }

        $violations = $this->validator->validate(new LayoutPresetSpecificationDtoCollection($dtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::layoutPresetsInvalid($violations);
        }

        return $dtos;
    }
}
