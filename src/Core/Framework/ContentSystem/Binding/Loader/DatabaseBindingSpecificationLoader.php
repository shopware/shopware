<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A persisted row is runtime data that can drift after install (a dependency deactivated, a column
 * hand-edited): a row whose schema fails to decode or validate is skipped and logged at warning
 * level rather than aborting the whole load, unlike {@see YamlBindingSpecificationLoader}, which
 * fails hard on an authored file.
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
        private readonly LoggerInterface $logger,
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
                $this->logger->warning(\sprintf('Skipping binding specification "%s:<unknown>": persisted row has no name and cannot be registered', $source), [
                    'source' => $source,
                ]);

                continue;
            }

            $name = $row['name'];
            $identifier = $source . ':' . $name;

            try {
                $schema = json_decode($row['schema'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning(\sprintf('Skipping binding specification "%s": invalid JSON schema: %s', $identifier, $e->getMessage()), [
                    'identifier' => $identifier,
                    'reason' => $e->getMessage(),
                ]);

                continue;
            }

            if (!\is_array($schema)) {
                $this->logger->warning(\sprintf('Skipping binding specification "%s": persisted schema must decode to an array/map, got %s', $identifier, get_debug_type($schema)), [
                    'identifier' => $identifier,
                    'type' => get_debug_type($schema),
                ]);

                continue;
            }

            try {
                $dto = $this->serializer->denormalize($schema);

                $violations = $this->validator->validate(new BindingSpecificationDtoCollection([$name => $dto]));
            } catch (\Throwable $e) {
                $this->logger->warning(\sprintf('Skipping binding specification "%s": invalid schema: %s', $identifier, $e->getMessage()), [
                    'identifier' => $identifier,
                    'reason' => $e->getMessage(),
                ]);

                continue;
            }

            if ($violations->count() > 0) {
                $messages = [];
                foreach ($violations as $violation) {
                    $messages[] = $violation->getMessage();
                }

                $this->logger->warning(\sprintf('Skipping binding specification "%s": validation failed: %s', $identifier, implode('; ', $messages)), [
                    'identifier' => $identifier,
                    'reason' => implode('; ', $messages),
                ]);

                continue;
            }

            $resolved[] = new ResolvedBindingSpecificationDto($name, $source, $dto);
        }

        return array_map(
            static fn (ResolvedBindingSpecificationDto $resolvedDto) => $resolvedDto->toSpecification(),
            $resolved,
        );
    }
}
