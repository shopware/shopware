<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('fundamentals@after-sales')]
class ToOneSerializer extends FieldSerializer
{
    /**
     * @internal
     */
    public function __construct(private readonly PrimaryKeyResolver $primaryKeyResolver)
    {
    }

    /**
     * @param mixed $record
     *
     * @return iterable<string, mixed>
     */
    public function serialize(Config $config, Field $toOne, $record): iterable
    {
        if (!$toOne instanceof ManyToOneAssociationField && !$toOne instanceof OneToOneAssociationField) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore shopware.domainException (Will be removed in v6.8.0.0) */
                throw new \InvalidArgumentException('Expected *ToOneField');
            }
            throw ImportExportException::invalidInstanceType('toOne', ManyToOneAssociationField::class . '|' . OneToOneAssociationField::class);
        }

        if ($record === null) {
            return null;
        }

        if ($record instanceof Struct) {
            $record = $record->jsonSerialize();
        }

        $definition = $toOne->getReferenceDefinition();
        $entitySerializer = $this->serializerRegistry->getEntity($definition->getEntityName());

        $result = $entitySerializer->serialize($config, $definition, $record);
        yield $toOne->getPropertyName() => iterator_to_array($result);
    }

    /**
     * @param mixed $records
     */
    public function deserialize(Config $config, Field $toOne, $records): mixed
    {
        if (!$toOne instanceof ManyToOneAssociationField && !$toOne instanceof OneToOneAssociationField) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore shopware.domainException (Will be removed in v6.8.0.0) */
                throw new \InvalidArgumentException('Expected *ToOneField');
            }
            throw ImportExportException::invalidInstanceType('toOne', ManyToOneAssociationField::class . '|' . OneToOneAssociationField::class);
        }

        $definition = $toOne->getReferenceDefinition();
        $entitySerializer = $this->serializerRegistry->getEntity($definition->getEntityName());
        $records = $this->primaryKeyResolver->resolvePrimaryKeyFromUpdatedBy($config, $definition, $records);

        $result = $entitySerializer->deserialize($config, $definition, $records);

        if (!\is_array($result)) {
            $result = iterator_to_array($result);
        }
        if (empty($result)) {
            return null;
        }

        return $result;
    }

    public function supports(Field $field): bool
    {
        return $field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField;
    }
}
