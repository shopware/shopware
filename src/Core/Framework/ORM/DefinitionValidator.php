<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM;

use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\Write\Flag\Extension;
use Shopware\Core\Framework\Struct\ArrayStruct;

class DefinitionValidator
{
    private const IGNORE_FIELDS = [
        'product.cover',
        'customer.defaultBillingAddress',
        'customer.defaultShippingAddress',
        'customer.activeShippingAddress',
        'customer.activeBillingAddress',
        'product_configurator.selected',
    ];
    /**
     * @var DefinitionRegistry
     */
    protected $registry;

    public function __construct(DefinitionRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function validate()
    {
        $violations = [];

        foreach ($this->registry->getElements() as $definition) {
            $violations[$definition] = [];
        }

        /** @var string|EntityDefinition $definition */
        foreach ($this->registry->getElements() as $definition) {
            $instance = new $definition();

            if ($instance instanceof MappingEntityDefinition) {
                continue;
            }

            $struct = $definition::getStructClass();

            if ($struct !== ArrayStruct::class) {
                $violations[$definition] = array_merge(
                    $violations[$definition],
                    $this->validateStruct($struct, $definition)
                );
            }

            $violations = array_merge_recursive($violations, $this->validateAssociations($definition));
        }

        $violations = array_filter($violations, function ($vio) {
            return !empty($vio);
        });

        return $violations;
    }

    public function getNotices(): array
    {
        $notices = [];
        /** @var string|EntityDefinition $definition */
        foreach ($this->registry->getElements() as $definition) {
            $notices[$definition] = [];
        }

        foreach ($this->registry->getElements() as $definition) {
            $instance = new $definition();

            if ($instance instanceof MappingEntityDefinition) {
                continue;
            }
            $struct = $definition::getStructClass();

            if ($struct !== ArrayStruct::class) {
                $notices[$definition] = array_merge_recursive(
                    $notices[$definition],
                    $this->findStructNotices($struct, $definition)
                );
            }
        }

        return array_filter($notices, function ($vio) {
            return !empty($vio);
        });
    }

    private function findStructNotices(string $struct, string $definition): array
    {
        $reflection = new \ReflectionClass($struct);

        /** @var string|EntityDefinition $definition */
        $fields = $definition::getFields();

        $notices = [];
        foreach ($reflection->getProperties() as $property) {
            $key = $definition::getEntityName() . '.' . $property->getName();
            if (in_array($key, self::IGNORE_FIELDS, true)) {
                continue;
            }

            if ($reflection->getParentClass() === MappingEntityDefinition::class) {
                continue;
            }

            if (in_array($property->getName(), ['id', 'tenantId', 'extensions'])) {
                continue;
            }

            if (!$fields->get($property->getName())) {
                $notices[] = sprintf('Missing field %s in %s', $property->getName(), $definition);
            }
        }

        return $notices;
    }

    private function validateStruct(string $struct, string $definition): array
    {
        $reflection = new \ReflectionClass($struct);

        /** @var string|EntityDefinition $definition */
        $fields = $definition::getFields();

        $properties = [];
        $functionViolations = [];

        foreach ($fields as $field) {
            if ($field instanceof TenantIdField || $field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }

            if ($field->is(Extension::class)) {
                continue;
            }

            $key = $definition::getEntityName() . '.' . $field->getPropertyName();
            if (in_array($key, self::IGNORE_FIELDS, true)) {
                continue;
            }

            $propertyName = $field->getPropertyName();

            $setter = 'set' . ucfirst($propertyName);
            $getter = 'get' . ucfirst($propertyName);

            if (!$reflection->hasProperty($propertyName)) {
                $properties[] = sprintf('Missing property %s in %s', $propertyName, $struct);
            }

            if (!$reflection->hasMethod($getter)) {
                $functionViolations[] = sprintf('No getter function %s in %s', $getter, $struct);
            }

            if (!$reflection->hasMethod($setter)) {
                $functionViolations[] = sprintf('No setter function %s in %s', $setter, $struct);
            }
        }

        return array_merge($properties, $functionViolations);
    }

    private function validateAssociations(string $definition): array
    {
        $violations = [];

        /** @var string|EntityDefinition $definition */
        $associations = $definition::getFields()->filterInstance(AssociationInterface::class);

        /** @var AssociationInterface $association */
        foreach ($associations as $association) {
            $key = $definition::getEntityName() . '.' . $association->getPropertyName();

            if (in_array($key, self::IGNORE_FIELDS, true)) {
                continue;
            }

            if ($association->is(Extension::class)) {
                continue;
            }

            if ($association instanceof OneToManyAssociationField) {
                $violations = array_merge_recursive(
                    $violations,
                    $this->validateOneToMany($definition, $association)
                );

                continue;
            }

            if ($association instanceof ManyToOneAssociationField) {
                $violations = array_merge_recursive(
                    $violations,
                    $this->validateManyToOne($definition, $association)
                );

                continue;
            }

            if ($association instanceof ManyToManyAssociationField) {
                continue;
            }
        }

        return $violations;
    }

    private function validateManyToOne(string $definition, ManyToOneAssociationField $association): array
    {
        $associationViolations = [];
        $reference = $association->getReferenceClass();

        /** @var string|EntityDefinition $definition */
        $reverseSide = $reference::getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof OneToManyAssociationField && !$field instanceof TranslationsAssociationField) {
                    return false;
                }
                $reference = $field->getReferenceClass();

                return $field->getLocalField() === $association->getReferenceField() && $reference === $definition;
            }
        )->first();

        if (!$reverseSide) {
            $associationViolations[$definition][] = sprintf(
                'Missing reverse one to many association for %s <-> %s (%s)',
                $definition,
                $association->getReferenceClass(),
                $association->getPropertyName()
            );
        }

        return $associationViolations;
    }

    /**
     * @param string $definition
     * @param $association
     *
     * @return array
     */
    private function validateOneToMany(string $definition, OneToManyAssociationField $association): array
    {
        $associationViolations = [];
        $reference = $association->getReferenceClass();

        /** @var string|EntityDefinition $definition */
        $reverseSide = $reference::getFields()->filter(
            function (Field $field) use ($association, $definition) {
                if (!$field instanceof ManyToOneAssociationField) {
                    return false;
                }

                return $field->getStorageName() === $association->getReferenceField() && $field->getReferenceClass() === $definition;
            }
        )->first();

        if (!$reverseSide) {
            $associationViolations[$definition][] = sprintf(
                'Association %s.%s has no reverse association in definition %s',
                $definition::getEntityName(),
                $association->getPropertyName(),
                $association->getReferenceClass()
            );
        }

        $foreignKey = $reference::getFields()->getByStorageName($association->getReferenceField());

        if (!$foreignKey instanceof FkField) {
            $associationViolations[$definition][] = sprintf(
                'Missing reference foreign key for column %s for definition association %s.%s',
                $association->getReferenceField(),
                $definition::getEntityName(),
                $association->getPropertyName()
            );
        }

        return $associationViolations;
    }
}
