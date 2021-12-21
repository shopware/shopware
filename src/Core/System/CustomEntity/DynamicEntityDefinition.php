<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal Used for custom entities
 */
class DynamicEntityDefinition extends EntityDefinition
{
    protected string $name;

    protected array $fieldDefinitions;

    public static function create(string $name, array $fields): DynamicEntityDefinition
    {
        $self = new self();
        $self->name = $name;
        $self->fieldDefinitions = $fields;

        return $self;
    }

    public function getEntityName(): string
    {
        return $this->name;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
        ]);

        foreach ($this->fieldDefinitions as $definition) {
            $name = $definition['name'];

            switch ($definition['type']) {
                case 'int':
                    $collection->add(new IntField($name, self::kebabCaseToCamelCase($name)));

                    break;
                case 'bool':
                    $collection->add(new BoolField($name, self::kebabCaseToCamelCase($name)));

                    break;
                case 'float':
                    $collection->add(new FloatField($name, self::kebabCaseToCamelCase($name)));

                    break;
                case 'string':
                    $collection->add(new StringField($name, self::kebabCaseToCamelCase($name)));

                    break;
                case 'email':
                    $collection->add(new EmailField($name, self::kebabCaseToCamelCase($name)));

                    break;
                case 'text':
                    $collection->add((new LongTextField($name, self::kebabCaseToCamelCase($name)))->addFlags(new AllowHtml(true)));

                    break;
                case 'json':
                    $collection->add(new JsonField($name, self::kebabCaseToCamelCase($name)));

                    break;
                case 'many-to-many':
                    $mapping = [$this->getEntityName(), $definition['reference']];
                    sort($mapping);

                    $collection->add(
                        new ManyToManyAssociationField(
                            self::kebabCaseToCamelCase($name),
                            DynamicEntityDefinition::class,
                            DynamicMappingEntityDefinition::class,
                            $this->getEntityName() . '_id',
                            $definition['reference'] . '_id',
                            'id',
                            'id',
                            implode('_', $mapping),
                            $definition['reference']
                        )
                    );

                    break;

                case 'many-to-one':
                    $collection->add(
                        new FkField(
                            $name . '_id',
                            self::kebabCaseToCamelCase($name) . 'Id',
                            DynamicEntityDefinition::class,
                            'id',
                            $definition['reference']
                        )
                    );

                    $collection->add(
                        new ManyToOneAssociationField(
                            self::kebabCaseToCamelCase($name),
                            $name . '_id',
                            DynamicEntityDefinition::class,
                            'id',
                            false,
                            $definition['reference']
                        )
                    );

                    break;
                case 'one-to-one':

                    $collection->add(
                        new FkField(
                            $name . '_id',
                            self::kebabCaseToCamelCase($name),
                            DynamicEntityDefinition::class,
                            'id',
                            $definition['reference']
                        )
                    );

                    $collection->add(
                        new OneToOneAssociationField(
                            self::kebabCaseToCamelCase($name),
                            $name . '_id',
                            'id',
                            DynamicEntityDefinition::class,
                            true,
                            $definition['reference']
                        )
                    );

                    break;
                case 'one-to-many':
                    $collection->add(
                        new OneToManyAssociationField(
                            self::kebabCaseToCamelCase($name),
                            DynamicEntityDefinition::class,
                            $this->getEntityName() . '_id',
                            'id',
                            $definition['reference']
                        )
                    );

                    break;
                default:
                    $collection->add(new StringField($name, self::kebabCaseToCamelCase($name)));

                    break;
            }
        }

        return $collection;
    }

    protected static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }
}
