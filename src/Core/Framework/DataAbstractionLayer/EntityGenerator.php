<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class EntityGenerator
{
    private string $classTemplate = <<<EOF
<?php declare(strict_types=1);

namespace #domain#;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
#uses#

class #entity#Entity extends Entity
{
    use EntityIdTrait;

    #properties#

#functions#
}
EOF;

    private string $propertyTemplate = <<<EOF
    /**
     * @var #type##nullable#
     */
    protected $#property#;
EOF;

    private string $propertyFunctions = <<<EOF
    public function get#propertyUc#(): #nullable##type#
    {
        return \$this->#propertyLc#;
    }

    public function set#propertyUc#(#nullable##type# $#propertyLc#): void
    {
        \$this->#propertyLc# = $#propertyLc#;
    }
EOF;

    private string $collectionTemplate = <<<EOF
<?php declare(strict_types=1);

namespace #domain#;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package core
 * @method void                add(#entityClass# \$entity)
 * @method void                set(string \$key, #entityClass# \$entity)
 * @method #entityClass#[]    getIterator()
 * @method #entityClass#[]    getElements()
 * @method #entityClass#|null get(string \$key)
 * @method #entityClass#|null first()
 * @method #entityClass#|null last()
 */
class #entity#Collection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return #entityClass#::class;
    }
}
EOF;

    public function generate(EntityDefinition $definition): ?array
    {
        if ($definition instanceof MappingEntityDefinition) {
            return null;
        }
        $entity = $definition->getEntityName();
        $entity = explode('_', $entity);
        $entity = array_map('ucfirst', $entity);
        $entity = implode('', $entity);

        $struct = $this->generateEntity($definition);

        $collection = $this->generateCollection($definition);

        return [
            $entity . 'Entity.php' => $struct,
            $entity . 'Collection.php' => $collection,
        ];
    }

    private function generateEntity(EntityDefinition $definition)
    {
        $properties = [];

        $uses = [];

        foreach ($definition->getFields() as $field) {
            $property = $this->generateProperty($definition, $field);
            if (!$property) {
                continue;
            }
            foreach ($property['uses'] as $use) {
                $uses[] = $use;
            }
            $properties[] = $property;
        }

        $functions = array_column($properties, 'functions');
        $properties = array_column($properties, 'property');

        $domain = explode('\\', $definition->getClass());
        $domain = \array_slice($domain, 0, \count($domain) - 1);
        $domain = implode('\\', $domain);

        $entity = $definition->getEntityName();
        $entity = explode('_', $entity);
        $entity = array_map('ucfirst', $entity);
        $entity = implode('', $entity);

        $parameters = [
            '#domain#' => $domain,
            '#uses#' => implode(";\n", $uses) . ';',
            '#entity#' => ucfirst($entity),
            '#properties#' => implode("\n\n    ", $properties),
            '#functions#' => implode("\n\n", $functions),
        ];

        return str_replace(
            array_keys($parameters),
            array_values($parameters),
            $this->classTemplate
        );
    }

    private function generateProperty(EntityDefinition $definition, Field $field): ?array
    {
        $nullable = '|null';
        if ($field->is(Required::class)) {
            $nullable = '';
        }

        $uses = [];

        switch (true) {
            case $field instanceof ParentAssociationField:
                $uses[] = $this->getUsage($definition->getEntityClass());
                $type = $this->getClassTypeHint($definition->getEntityClass());

                break;
            case $field instanceof ChildrenAssociationField:
                $uses[] = $this->getUsage($definition->getCollectionClass());
                $type = $this->getClassTypeHint($definition->getCollectionClass());

                break;
            case $field instanceof OneToOneAssociationField:
            case $field instanceof ManyToOneAssociationField:
                $uses[] = $this->getUsage($field->getReferenceDefinition()->getEntityClass());
                $type = $this->getClassTypeHint($field->getReferenceDefinition()->getEntityClass());

                break;
            case $field instanceof OneToManyAssociationField:
                $uses[] = $this->getUsage($field->getReferenceDefinition()->getCollectionClass());
                $type = $this->getClassTypeHint($field->getReferenceDefinition()->getCollectionClass());

                break;
            case $field instanceof ManyToManyAssociationField:
                $uses[] = $this->getUsage($field->getToManyReferenceDefinition()->getCollectionClass());
                $type = $this->getClassTypeHint($field->getToManyReferenceDefinition()->getCollectionClass());

                break;
            case $field instanceof ReferenceVersionField:
            case $field instanceof VersionField:
                return null;
            case $field instanceof TranslatedField:
                return $this->generateProperty(
                    $definition,
                    EntityDefinitionQueryHelper::getTranslatedField($definition, $field)
                );
            case $field instanceof CartPriceField:
                $type = 'CartPrice';
                $uses[] = 'use ' . CartPrice::class;

                break;
            case $field instanceof CalculatedPriceField:
                $type = 'CalculatedPrice';
                $uses[] = 'use ' . CalculatedPrice::class;

                break;
            case $field instanceof PriceDefinitionField:
                $type = 'QuantityPriceDefinition';
                $uses[] = 'use ' . QuantityPriceDefinition::class;

                break;
            case $field instanceof PriceField:
                $type = 'Price';
                $uses[] = 'use ' . Price::class;

                break;
            case $field instanceof FloatField:
                $type = 'float';

                break;
            case $field instanceof IntField:
                $type = 'int';

                break;
            case $field instanceof JsonField:
                $type = 'array';

                break;
            case $field instanceof LongTextField:
            case $field instanceof PasswordField:
            case $field instanceof IdField:
            case $field instanceof FkField:
            case $field instanceof StringField:
            case $field instanceof RemoteAddressField:
                $type = 'string';

                break;
            case $field instanceof BoolField:
                $type = 'bool';

                break;
            case $field instanceof DateTimeField:
            case $field instanceof DateField:
                $type = "\DateTimeInterface";

                break;
            case $field instanceof BlobField:
                $type = 'object';

                break;
            default:
                throw new \RuntimeException(sprintf('Unknown field %s', $field::class));
        }

        $template = str_replace(
            ['#property#', '#type#', '#nullable#'],
            [$field->getPropertyName(), $type, $nullable],
            $this->propertyTemplate
        );

        $nullable = '?';
        if ($field->is(Required::class)) {
            $nullable = '';
        }

        $functions = str_replace(
            ['#propertyUc#', '#propertyLc#', '#nullable#', '#type#'],
            [ucfirst($field->getPropertyName()), lcfirst($field->getPropertyName()), $nullable, $type],
            $this->propertyFunctions
        );

        return [
            'property' => trim($template),
            'uses' => $uses,
            'functions' => $functions,
        ];
    }

    private function generateCollection(EntityDefinition $definition)
    {
        $entityClass = $definition->getEntityClass();
        $entityClass = explode('\\', $entityClass);
        $entityClass = array_pop($entityClass);

        $entity = $definition->getEntityName();
        $entity = explode('_', $entity);
        $entity = array_map('ucfirst', $entity);
        $entity = implode('', $entity);

        $domain = explode('\\', $definition->getClass());
        $domain = \array_slice($domain, 0, \count($domain) - 1);
        $domain = implode('\\', $domain);

        $parameters = [
            '#domain#' => $domain,
            '#entityClass#' => $entityClass,
            '#entity#' => $entity,
        ];

        return str_replace(
            array_keys($parameters),
            array_values($parameters),
            $this->collectionTemplate
        );
    }

    private function getUsage(string $class)
    {
        return 'use ' . $class;
    }

    private function getClassTypeHint(string $class)
    {
        $parts = explode('\\', $class);

        return array_pop($parts);
    }
}
