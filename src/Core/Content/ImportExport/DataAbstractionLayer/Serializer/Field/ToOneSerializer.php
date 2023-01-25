<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
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
            throw new \InvalidArgumentException('Expected *ToOneField');
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
        if ($record !== null) {
            yield $toOne->getPropertyName() => iterator_to_array($result);
        }
    }

    /**
     * @param mixed $records
     *
     * @return mixed
     */
    public function deserialize(Config $config, Field $toOne, $records)
    {
        if (!$toOne instanceof ManyToOneAssociationField && !$toOne instanceof OneToOneAssociationField) {
            throw new \InvalidArgumentException('Expected *ToOneField');
        }

        $definition = $toOne->getReferenceDefinition();
        $entitySerializer = $this->serializerRegistry->getEntity($definition->getEntityName());
        /** @var \Traversable<mixed> $records */
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
