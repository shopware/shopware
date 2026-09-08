<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DatabaseLayoutPresetLoader extends AbstractContentSystemLayoutPresetLoader
{
    public function __construct(
        private readonly LayoutPresetSerializer $serializer,
        private readonly Connection $connection,
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<ContentSystemLayoutPresetSpecification>
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

        $presets = [];

        foreach ($rows as $row) {
            $identifier = 'app:' . $row['app_name'] . ':' . ($row['name'] ?: '<unknown>');

            try {
                $data = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning(\sprintf('Skipping layout preset "%s": invalid JSON data: %s', $identifier, $e->getMessage()));

                continue;
            }

            if (!\is_array($data)) {
                $this->logger->warning(\sprintf('Skipping layout preset "%s": persisted data must decode to an array/map, got %s', $identifier, get_debug_type($data)));

                continue;
            }

            try {
                $presets[] = $this->serializer->denormalize($data, $row['name']);
            } catch (\Throwable $e) {
                $this->logger->warning(\sprintf('Skipping layout preset "%s": %s', $identifier, $e->getMessage()));
            }
        }

        return $presets;
    }
}
