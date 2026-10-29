<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ResetOnClone;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\WasModifiedByUserField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Turns the serialized source entity into the write payload of its clone: which associations to read, which
 * properties to copy, new ids for the cloned rows, and the references between them pointed at the clone.
 *
 * @internal
 */
#[Package('framework')]
final class ClonePayloadBuilder
{
    /**
     * How many levels of a parent-child tree (product variants, category children) a clone reads along.
     * The walk recurses over the definition, so a self-referencing children association needs a stop;
     * the number is arbitrary, taken over from NEXT-1398 as deep enough for any real tree.
     */
    private const MAX_CHILDREN_DEPTH = 30;

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function isCloneProtected(EntityDefinition $definition, Context $context): bool
    {
        $protection = $definition->getProtections()->get(CloneProtection::class);

        return $protection !== null && !$protection->isAllowed($context->getScope());
    }

    /**
     * Adds every clone-relevant association to the criteria the source entity is read with.
     */
    public function addCloneAssociations(EntityDefinition $definition, Criteria $criteria, bool $cloneChildren, Context $context): void
    {
        $this->addCloneAssociationsRecursively($definition, $criteria, $cloneChildren, $context, 1);
    }

    /**
     * @param array<string, mixed> $data the serialized source entity
     * @param array<string, mixed> $overwrites
     *
     * @return array<string, mixed>
     */
    public function build(
        EntityDefinition $definition,
        array $data,
        string $sourceId,
        string $newId,
        array $overwrites,
        Context $context
    ): array {
        // A version of an entity keeps every id; a copy gets new ones.
        $keepIds = $newId === $sourceId;

        $clonedIds = new CloneIdMap();
        $data = $this->filterPropertiesForClone($definition, $data, $keepIds, $sourceId, $definition, $context, $clonedIds);
        $data['id'] = $newId;

        if (!$clonedIds->isEmpty()) {
            $data = $this->remapForeignKeys($definition, $data, $clonedIds);
        }

        $createdAtField = $definition->getField('createdAt');
        $updatedAtField = $definition->getField('updatedAt');

        if ($createdAtField instanceof DateTimeField) {
            $data['createdAt'] = $this->clock->now();
        }

        if ($updatedAtField instanceof DateTimeField) {
            if ($updatedAtField->getFlag(Required::class)) {
                $data['updatedAt'] = $this->clock->now();
            } else {
                $data['updatedAt'] = null;
            }
        }

        $this->validateOverwriteWriteProtection($definition, $overwrites, $context);

        return Feature::isActive('v6.8.0.0')
            ? $this->mergeOverwrites($definition, $data, $overwrites)
            : array_replace_recursive($data, $overwrites);
    }

    private function addCloneAssociationsRecursively(
        EntityDefinition $definition,
        Criteria $criteria,
        bool $cloneChildren,
        Context $context,
        int $childCounter
    ): void {
        // add all cascade delete associations
        $cascades = $definition->getFields()->filter(static function (Field $field) {
            $flag = $field->getFlag(CascadeDelete::class);

            return $flag ? $flag->isCloneRelevant() : false;
        });

        foreach ($cascades as $cascade) {
            // the flag only means something on an association
            if (!$cascade instanceof AssociationField) {
                continue;
            }

            if ($this->isCloneProtected($this->getCloneReferenceDefinition($cascade), $context)) {
                continue;
            }

            $nested = $criteria->getAssociation($cascade->getPropertyName());

            if ($cascade instanceof ManyToManyAssociationField) {
                continue;
            }

            // many to one shouldn't be cascaded
            if ($cascade instanceof ManyToOneAssociationField) {
                continue;
            }

            $reference = $cascade->getReferenceDefinition();

            $childrenAware = $reference->isChildrenAware();

            // first level of parent-child tree?
            if ($childrenAware && $reference !== $definition) {
                // where product.children.parentId IS NULL
                $nested->addFilter(new EqualsFilter($reference->getEntityName() . '.parentId', null));
            }

            if ($cascade instanceof ChildrenAssociationField) {
                if ($childCounter >= self::MAX_CHILDREN_DEPTH || !$cloneChildren) {
                    $criteria->removeAssociation($cascade->getPropertyName());

                    continue;
                }

                ++$childCounter;
                $this->addCloneAssociationsRecursively($reference, $nested, $cloneChildren, $context, $childCounter);

                continue;
            }

            $this->addCloneAssociationsRecursively($reference, $nested, $cloneChildren, $context, 1);
        }
    }

    /**
     * @param array<string, mixed> $overwrites
     */
    private function validateOverwriteWriteProtection(EntityDefinition $definition, array $overwrites, Context $context): void
    {
        foreach ($overwrites as $propertyName => $value) {
            $field = $definition->getFields()->get($propertyName);
            $writeProtection = $field?->getFlag(WriteProtected::class);

            if ($writeProtection === null || $writeProtection->isAllowed($context->getScope())) {
                continue;
            }

            $message = 'This field is write-protected.';
            $allowedScopes = implode(' or ', $writeProtection->getAllowedScopes());

            if ($allowedScopes !== '') {
                $message .= ' (Got: "%s" scope and "%s" is required)';
            }

            throw DataAbstractionLayerException::invalidWriteConstraintViolation(
                new ConstraintViolationList([
                    new ConstraintViolation(
                        \sprintf($message, $context->getScope(), $allowedScopes),
                        $message,
                        [$context->getScope(), $allowedScopes],
                        $value,
                        $propertyName,
                        $value
                    ),
                ]),
                '/' . $propertyName
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function filterPropertiesForClone(
        EntityDefinition $definition,
        array $data,
        bool $keepIds,
        string $cloneId,
        EntityDefinition $cloneDefinition,
        Context $context,
        CloneIdMap $clonedIds
    ): array {
        $extensions = [];
        $payload = [];

        $fields = $definition->getFields();

        foreach ($fields as $field) {
            if ($field->is(ResetOnClone::class)) {
                continue;
            }

            $writeProtection = $field->getFlag(WriteProtected::class);
            if ($writeProtection && !$writeProtection->isAllowed(Context::SYSTEM_SCOPE)) {
                continue;
            }

            // Autoloaded associations are not added to the clone criteria, but can still be present in the serialized entity.
            if ($field instanceof AssociationField && $this->isCloneProtected($this->getCloneReferenceDefinition($field), $context)) {
                continue;
            }

            // set data and payload cursor to root or extensions to simplify following if conditions
            $dataCursor = $data;

            $payloadCursor = &$payload;

            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }

            // wasModifiedByUser is derived from the write scope and rejects explicit values;
            // a clone is a fresh, system-created record, so let the serializer set it to false.
            if ($field instanceof WasModifiedByUserField) {
                continue;
            }

            if ($field->is(Extension::class)) {
                $dataCursor = $data['extensions'] ?? [];
                $payloadCursor = &$extensions;
                if (isset($dataCursor['foreignKeys'])) {
                    $fields = $definition->getFields();
                    foreach ($dataCursor['foreignKeys'] as $key => $value) {
                        \assert(\is_string($key));
                        // Clone FK extension and add it to payload
                        if (\is_string($value) && Uuid::isValid($value) && $fields->has($key) && $fields->get($key) instanceof FkField) {
                            $payload[$key] = $value;
                        }
                    }
                }
            }

            if (!\array_key_exists($field->getPropertyName(), $dataCursor)) {
                continue;
            }

            if (!$keepIds && $field instanceof ParentFkField) {
                continue;
            }

            $value = $dataCursor[$field->getPropertyName()];

            // remove reference of cloned entity in all sub entity routes. Appears in a parent-child nested data tree
            if ($field instanceof FkField && !$keepIds && $value === $cloneId && $cloneDefinition === $field->getReferenceDefinition()) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            // scalar value? assign directly
            if (!$field instanceof AssociationField) {
                $payloadCursor[$field->getPropertyName()] = $value;

                continue;
            }

            // many to one should be skipped because it is no part of the root entity
            if ($field instanceof ManyToOneAssociationField) {
                continue;
            }

            $flag = $field->getFlag(CascadeDelete::class);
            if (!$flag || !$flag->isCloneRelevant()) {
                continue;
            }

            if ($field instanceof OneToManyAssociationField) {
                $reference = $field->getReferenceDefinition();

                $nested = [];
                foreach ($value as $item) {
                    $nestedItem = $this->filterPropertiesForClone($reference, $item, $keepIds, $cloneId, $cloneDefinition, $context, $clonedIds);

                    if (!$keepIds) {
                        $nestedItem = $this->replacePrimaryKey($field, $nestedItem, $clonedIds);
                    }

                    $nested[] = $nestedItem;
                }

                $nested = array_filter($nested);
                if ($nested === []) {
                    continue;
                }

                $payloadCursor[$field->getPropertyName()] = $nested;

                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $nested = [];

                foreach ($value as $item) {
                    $nested[] = ['id' => $item['id']];
                }

                if ($nested === []) {
                    continue;
                }

                $payloadCursor[$field->getPropertyName()] = $nested;

                continue;
            }

            if ($field instanceof OneToOneAssociationField && $value) {
                $reference = $field->getReferenceDefinition();

                $nestedItem = $this->filterPropertiesForClone($reference, $value, $keepIds, $cloneId, $cloneDefinition, $context, $clonedIds);

                if (!$keepIds) {
                    $nestedItem = $this->replacePrimaryKey($field, $nestedItem, $clonedIds);
                }

                $payloadCursor[$field->getPropertyName()] = $nestedItem;
            }
        }

        if ($extensions !== []) {
            $payload['extensions'] = $extensions;
        }

        return $payload;
    }

    /**
     * Gives a cloned nested row a new id and records it, so references to the row can be rewritten once the whole
     * payload is built. Primary keys that are not an id are removed and set by the parent.
     *
     * @param array<string, mixed> $nestedItem
     *
     * @return array<string, mixed>
     */
    private function replacePrimaryKey(AssociationField $field, array $nestedItem, CloneIdMap $clonedIds): array
    {
        $reference = $field->getReferenceDefinition();

        foreach ($reference->getPrimaryKeys() as $pkField) {
            /*
             * `EntityTranslationDefinition` doesn't have an `id`; they use a composite primary key consisting of the
             * entity id and the `languageId`. When cloning the entity, we want to copy the `languageId`. The entity id
             * has to be unset so that it's set by the parent, resulting in a valid primary key.
             */
            if (
                $field instanceof TranslationsAssociationField
                && $pkField instanceof StorageAware
                && $pkField->getStorageName() === $field->getLanguageField()
            ) {
                continue;
            }

            $property = $pkField->getPropertyName();
            if (!\array_key_exists($property, $nestedItem)) {
                continue;
            }

            $sourceId = $nestedItem[$property];
            if ($pkField instanceof IdField && \is_string($sourceId)) {
                $nestedItem[$property] = $clonedIds->cloneIdFor($reference->getEntityName(), $sourceId);

                continue;
            }

            unset($nestedItem[$property]);
        }

        return $nestedItem;
    }

    /**
     * Points every foreign key that referenced a cloned row at the clone of that row. The source order's
     * delivery, for example, references the source order's address; the cloned delivery has to reference the
     * cloned address.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function remapForeignKeys(EntityDefinition $definition, array $payload, CloneIdMap $clonedIds): array
    {
        foreach ($definition->getFields() as $field) {
            $property = $field->getPropertyName();
            if (!\array_key_exists($property, $payload)) {
                continue;
            }

            $value = $payload[$property];

            if ($field instanceof FkField && !$field instanceof ReferenceVersionField) {
                if (\is_string($value)) {
                    $payload[$property] = $clonedIds->get($field->getReferenceDefinition()->getEntityName(), $value) ?? $value;
                }

                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            if ($field instanceof OneToManyAssociationField) {
                $items = [];
                foreach ($value as $key => $item) {
                    $items[$key] = \is_array($item) ? $this->remapForeignKeys($field->getReferenceDefinition(), $item, $clonedIds) : $item;
                }

                $payload[$property] = $items;

                continue;
            }

            if ($field instanceof OneToOneAssociationField) {
                $payload[$property] = $this->remapForeignKeys($field->getReferenceDefinition(), $value, $clonedIds);
            }
        }

        // Extension associations sit one level down, under the same property names.
        if (isset($payload['extensions']) && \is_array($payload['extensions'])) {
            $payload['extensions'] = $this->remapForeignKeys($definition, $payload['extensions'], $clonedIds);
        }

        return $payload;
    }

    private function getCloneReferenceDefinition(AssociationField $field): EntityDefinition
    {
        if ($field instanceof ManyToManyAssociationField) {
            return $field->getToManyReferenceDefinition();
        }

        return $field->getReferenceDefinition();
    }

    /**
     * Merges overwrites into data, replacing list fields and merging nested property mappings recursively.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $overwrites
     * @param list<Field>|null $nestedFields Optional nested field definitions for recursive calls
     *
     * @return array<string, mixed>
     */
    private function mergeOverwrites(EntityDefinition $definition, array $data, array $overwrites, ?array $nestedFields = null): array
    {
        foreach ($overwrites as $key => $value) {
            $field = $this->getFieldDefinition($key, $definition, $nestedFields);

            // ListField should be completely replaced, not merged
            if ($field instanceof ListField) {
                $data[$key] = $value;
                continue;
            }

            $isBothArrays = \is_array($value) && \array_key_exists($key, $data) && \is_array($data[$key]);

            // For fields with nested property mappings, recursively handle them
            if ($isBothArrays && $field && method_exists($field, 'getPropertyMapping')) {
                $propertyMapping = $field->getPropertyMapping();
                if ($propertyMapping !== []) {
                    $data[$key] = $this->mergeOverwrites($definition, $data[$key], $value, $propertyMapping);
                    continue;
                }
            }

            // For other arrays, use array_replace_recursive; otherwise assign directly
            $data[$key] = $isBothArrays
                ? array_replace_recursive($data[$key], $value)
                : $value;
        }

        return $data;
    }

    /**
     * Get field definition from nested fields or entity definition.
     *
     * @param list<Field>|null $nestedFields
     */
    private function getFieldDefinition(string $key, EntityDefinition $definition, ?array $nestedFields): ?Field
    {
        if ($nestedFields !== null) {
            foreach ($nestedFields as $field) {
                if ($field->getPropertyName() === $key) {
                    return $field;
                }
            }

            return null;
        }

        return $definition->getFields()->get($key);
    }
}
