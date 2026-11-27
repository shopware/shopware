<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * Adds the reverse side of a many-to-many association declared with
 * {@see \Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany::$reversedBy}
 * to the target entity. The association is exposed through the entity extensions
 * of the target struct, as the target struct has no typed property for it.
 */
#[Package('framework')]
class AttributeManyToManyReverseExtension extends EntityExtension
{
    /**
     * @param list<string>|null $apiSources Null when the reverse association has no explicit ApiAware flag, otherwise the protected sources of the ApiAware flag (empty for all sources)
     * @param string|null $onDelete Value of the OnDelete enum for the reverse association, or null for no delete flag
     */
    public function __construct(
        private readonly string $entityName,
        private readonly string $propertyName,
        private readonly string $referenceEntity,
        private readonly string $mappingEntity,
        private readonly string $mappingLocalColumn,
        private readonly string $mappingReferenceColumn,
        private readonly ?array $apiSources = null,
        private readonly ?string $onDelete = null,
    ) {
    }

    public function extendFields(FieldCollection $collection): void
    {
        $field = new ManyToManyAssociationField(
            $this->propertyName,
            $this->referenceEntity,
            $this->mappingEntity,
            $this->mappingLocalColumn,
            $this->mappingReferenceColumn,
        );

        $flags = [];
        if ($this->apiSources !== null) {
            $flags[] = new ApiAware(...$this->apiSources);
        }
        $deleteFlag = match ($this->onDelete) {
            OnDelete::CASCADE->value => new CascadeDelete(),
            OnDelete::SET_NULL->value => new SetNullOnDelete(),
            OnDelete::RESTRICT->value => new RestrictDelete(),
            default => null,
        };
        if ($deleteFlag !== null) {
            $flags[] = $deleteFlag;
        }
        if ($flags !== []) {
            $field->addFlags(...$flags);
        }

        $collection->add($field);
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }
}
