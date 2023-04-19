<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class SyncFkResolver
{
    /**
     * @internal
     *
     * @param iterable<AbstractFkResolver> $resolvers
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly iterable $resolvers
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolve(string $entity, array $payload): array
    {
        $map = $this->collect($entity, $payload);

        if (empty($map)) {
            return $payload;
        }

        foreach ($map as $key => &$values) {
            $values = $this->getResolver($key)->resolve($values);
        }

        \array_walk_recursive($payload, function (&$value): void {
            $value = $value instanceof FkReference ? $value->resolved : $value;
        });

        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     *
     * @return array<string, array<FkReference>>
     */
    private function collect(string $entity, array &$payload): array
    {
        $definition = $this->registry->getByEntityName($entity);

        $map = [];
        foreach ($payload as &$row) {
            foreach ($row as $key => &$value) {
                if (\is_array($value) && isset($value['resolver']) && isset($value['value'])) {
                    $definition = $this->registry->getByEntityName($entity);

                    $field = $definition->getField($key);

                    $ref = match (true) {
                        $field instanceof FkField => $field->getReferenceDefinition()->getEntityName(),
                        $field instanceof IdField => $entity,
                        default => null
                    };

                    if ($ref === null) {
                        continue;
                    }

                    $resolver = (string) $value['resolver'];

                    $row[$key] = $reference = new FkReference($value['value']);

                    $map[$resolver][] = $reference;
                }

                if (\is_array($value)) {
                    $field = $definition->getField($key);

                    if (!$field instanceof AssociationField) {
                        continue;
                    }

                    $nested = [];
                    if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                        $ref = $field instanceof ManyToManyAssociationField ? $field->getToManyReferenceDefinition()->getEntityName() : $field->getReferenceDefinition()->getEntityName();
                        $nested = $this->collect($ref, $value);
                    } elseif ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                        $tmp = [$value];
                        $nested = $this->collect($field->getReferenceDefinition()->getEntityName(), $tmp);
                        $value = \array_shift($tmp);
                    }

                    $map = $this->merge($map, $nested);
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string, array<FkReference>> $map
     * @param array<string, array<FkReference>> $nested
     *
     * @return array<string, array<FkReference>>
     */
    private function merge(array $map, array $nested): array
    {
        foreach ($nested as $resolver => $values) {
            foreach ($values as $value) {
                $map[$resolver][] = $value;
            }
        }

        return $map;
    }

    private function getResolver(string $key): AbstractFkResolver
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver::getName() === $key) {
                return $resolver;
            }
        }

        throw ApiException::resolverNotFoundException($key);
    }
}
