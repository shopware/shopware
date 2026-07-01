<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDtoCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A persisted row is runtime data that can drift after install (a dependency deactivated, a column
 * hand-edited): a row whose schema fails to decode or validate is skipped and logged at warning
 * level rather than aborting the whole load, unlike YamlTypeLoader, which fails hard on an authored
 * file.
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
        private readonly LoggerInterface $logger,
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
            $name = $row['name'] ?: '<unknown>';
            $source = 'app:' . $row['app_name'];
            $identifier = $source . ':' . $name;

            try {
                $schema = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning(\sprintf('Skipping element type "%s": invalid JSON schema: %s', $identifier, $e->getMessage()), [
                    'identifier' => $identifier,
                    'reason' => $e->getMessage(),
                ]);

                continue;
            }

            if (!\is_array($schema)) {
                $this->logger->warning(\sprintf('Skipping element type "%s": persisted schema must decode to an array/map, got %s', $identifier, get_debug_type($schema)), [
                    'identifier' => $identifier,
                    'type' => get_debug_type($schema),
                ]);

                continue;
            }

            try {
                $dto = $this->serializer->denormalize($schema);
            } catch (\Throwable $e) {
                $this->logger->warning(\sprintf('Skipping element type "%s": invalid schema: %s', $identifier, $e->getMessage()), [
                    'identifier' => $identifier,
                    'reason' => $e->getMessage(),
                ]);

                continue;
            }

            $violations = $this->validator->validate(new ElementTypeSpecificationDtoCollection([$name => $dto]));
            if ($violations->count() > 0) {
                $messages = [];
                foreach ($violations as $violation) {
                    $messages[] = $violation->getMessage();
                }

                $this->logger->warning(\sprintf('Skipping element type "%s": validation failed: %s', $identifier, implode('; ', $messages)), [
                    'identifier' => $identifier,
                    'reason' => implode('; ', $messages),
                ]);

                continue;
            }

            $resolvedSpecificationDtos[] = new ResolvedElementTypeSpecificationDto($name, $source, $dto);
        }

        return array_map(
            static fn (ResolvedElementTypeSpecificationDto $resolvedSpecificationDto) => $resolvedSpecificationDto->toSpecification(),
            $resolvedSpecificationDtos,
        );
    }
}
