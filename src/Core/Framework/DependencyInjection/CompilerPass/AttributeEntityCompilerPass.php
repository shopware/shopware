<?php

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\CustomFields as CustomFieldsAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Fk;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Primary;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Protection;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ReferenceVersion;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Required as RequiredAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Version;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AsArray;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SerializedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AttributeEntityCompilerPass implements CompilerPassInterface
{
    private const FIELD_ATTRIBUTES = [
        Translations::class,
        Fk::class,
        Version::class,
        Field::class,
        OneToMany::class,
        ManyToMany::class,
        ManyToOne::class,
        OneToOne::class
    ];

    private const ASSOCIATIONS = [
        OneToMany::class,
        ManyToMany::class,
        ManyToOne::class,
        OneToOne::class
    ];

    private static function snake_case(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    public function process(ContainerBuilder $container): void
    {
        $services = $container->findTaggedServiceIds('shopware.entity');

        foreach ($services as $class => $_) {
            $reflection = new \ReflectionClass($class);

            $collection = $reflection->getAttributes(Entity::class);

            /** @var Entity $instance */
            $instance = $collection[0]->newInstance();

            $meta = [
                'entity_class' => $class,
                'entity_name' => $instance->name,
                'fields' => $this->parse($instance->name, $reflection, $container)
            ];

            $this->definition($meta, $container, $instance->name);

            $this->repository($container, $instance);

            $this->translation($meta, $container, $instance);
        }
    }

    private function parse(string $entity, \ReflectionClass $reflection, ContainerBuilder $container): array
    {
        $properties = $reflection->getProperties();

        $fields = [];
        foreach ($properties as $property) {
            $field = $this->parseField($entity, $property, $container);

            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function getAttribute(\ReflectionProperty $property, string ...$list): ?\ReflectionAttribute
    {
        foreach ($list as $attribute) {
            $attribute = $property->getAttributes($attribute);
            if (!empty($attribute)) {
                return $attribute[0];
            }
        }

        return null;
    }

    private function parseField(string $entity, \ReflectionProperty $property, ContainerBuilder $container): ?array
    {
        $attribute = $this->getAttribute($property, ...self::FIELD_ATTRIBUTES);

        if (!$attribute) {
            return null;
        }
        /** @var Field $field */
        $field = $attribute->newInstance();

        $field->nullable = $property->getType()?->allowsNull() ?? true;

        if ($field instanceof ManyToMany) {
            $this->mappings($entity, $field, $container);
        }

        return [
            'name' => $property->getName(),
            'class' => $this->getFieldClass($field),
            'flags' => $this->getFlags($field, $property),
            'translated' => $field->translated,
            'args' => $this->getFieldArgs($entity, $field, $property)
        ];
    }

    private function getFieldClass(Field $field): string
    {
        return match ($field->type) {
            FieldType::INT => IntField::class,
            FieldType::TEXT => LongTextField::class,
            FieldType::STRING => StringField::class,
            FieldType::FLOAT => FloatField::class,
            FieldType::BOOL => BoolField::class,
            FieldType::DATETIME => DateTimeField::class,
            FieldType::PRICE => PriceField::class,
            FieldType::UUID => IdField::class,
            FieldType::AUTO_INCREMENT => AutoIncrementField::class,
            CustomFieldsAttr::class => CustomFields::class,
            FieldType::SERIALIZED => SerializedField::class,
            FieldType::JSON => JsonField::class,
            FieldType::DATE => DateField::class,
            FieldType::DATE_INTERVAL => DateIntervalField::class,
            FieldType::EMAIL => EmailField::class,
            FieldType::LIST => ListField::class,
            FieldType::PASSWORD => PasswordField::class,
            FieldType::REMOTE_ADDRESS => RemoteAddressField::class,
            FieldType::TIME_ZONE => TimeZoneField::class,
            OneToMany::TYPE => OneToManyAssociationField::class,
            OneToOne::TYPE => OneToOneAssociationField::class,
            ManyToOne::TYPE => ManyToOneAssociationField::class,
            ManyToMany::TYPE => ManyToManyAssociationField::class,
            Fk::TYPE => FkField::class,
            Version::TYPE => VersionField::class,
            ReferenceVersion::TYPE => ReferenceVersionField::class,
            Translations::TYPE => TranslationsAssociationField::class
        };
    }

    private function getFieldArgs(string $entity, Field $field, \ReflectionProperty $property): array
    {
        $storage = self::snake_case($property->getName());

        /** @var OneToMany|ManyToMany|ManyToOne|OneToOne|Field $field */
        return match ($field->type) {
            Translations::TYPE => [$entity . '_translation', $entity . '_id'],
            Fk::TYPE => [$storage, $property->getName(), $field->entity],
            OneToOne::TYPE => [$property->getName(), $field->column ?? $storage . '_id', $field->ref, $field->entity],
            ManyToOne::TYPE => [$property->getName(), $storage . '_id', $field->entity, $field->ref],
            OneToMany::TYPE => [$property->getName(), $field->entity, $field->ref, 'id'],
            ManyToMany::TYPE => [$property->getName(), $field->entity, self::mapping($entity, $field), $entity . '_id', $field->entity . '_id'],
            Version::TYPE => [],
            ReferenceVersion::TYPE => [$field->entity, $storage],
            default => [$storage, $property->getName()]
        };
    }

    private static function mapping(string $entity, ManyToMany $field): string
    {
        $items = [$entity, $field->entity];
        sort($items);

        return implode('_', $items);
    }

    private function repository(ContainerBuilder $container, Entity $instance): void
    {
        $repository = new Definition(
            EntityRepository::class,
            [
                new Reference($instance->name . '.definition'),
                new Reference(EntityReaderInterface::class),
                new Reference(VersionManager::class),
                new Reference(EntitySearcherInterface::class),
                new Reference(EntityAggregatorInterface::class),
                new Reference('event_dispatcher'),
                new Reference(EntityLoadedEventFactory::class),
            ]
        );
        $repository->setPublic(true);

        $container->setDefinition($instance->name . '.repository', $repository);
    }

    public function definition(array $meta, ContainerBuilder $container, string $entity): void
    {
        $definition = new Definition(AttributeEntityDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $container->setDefinition($entity . '.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $registry->addMethodCall('register', [new Reference($entity . '.definition'), $entity . '.definition']);
    }

    private function translation(array $meta, ContainerBuilder $container, Entity $instance): void
    {
        if (!$this->hasTranslation($meta)) {
            return;
        }
        $definition = new Definition(AttributeTranslationDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $container->setDefinition($instance->name . '_translation.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $registry->addMethodCall('register', [new Reference($instance->name . '_translation.definition'), $instance->name . '_translation.definition']);
    }

    private function hasTranslation(array $meta): bool
    {
        foreach ($meta['fields'] as $field) {
            if ($field['translated']) {
                return true;
            }
        }
        return false;
    }

    private function getFlags(Field $field, \ReflectionProperty $property): array
    {
        $flags = [];

        if (!$field->nullable) {
            $flags[Required::class] = ['class' => Required::class];
        }

        if ($this->getAttribute($property, RequiredAttr::class)) {
            $flags[Required::class] = ['class' => Required::class];
        }

        if ($this->getAttribute($property, Primary::class)) {
            $flags[PrimaryKey::class] = ['class' => PrimaryKey::class];
            $flags[Required::class] = ['class' => Required::class];
        }

        if ($field->api !== false) {
            $flags[ApiAware::class] = ['class' => ApiAware::class, 'args' => $field->api === true ? [] : $field->api];
        }

        if ($protection = $this->getAttribute($property, Protection::class)) {
            $protection = $protection->newInstance();

            /** @var Protection $protection */
            $flags[WriteProtected::class] = ['class' => WriteProtected::class, 'args' => $protection->write];
        }

        if ($this->getAttribute($property, ManyToMany::class, OneToMany::class, Translations::class)) {
            if ($property->getType()?->getName() === 'array') {
                $flags[AsArray::class] = ['class' => AsArray::class];
            }
        }

        if ($association = $this->getAttribute($property, ...self::ASSOCIATIONS)) {
            $association = $association->newInstance();

            /** @var OneToMany|ManyToMany|ManyToOne|OneToOne $association */
            $flags['cascade'] = match($association->onDelete) {
                OnDelete::CASCADE => ['class' => CascadeDelete::class],
                OnDelete::SET_NULL => ['class' => SetNullOnDelete::class],
                OnDelete::RESTRICT => ['class' => RestrictDelete::class],
                default => null
            };
        }

        return $flags;
    }

    private function mappings(string $entity, ManyToMany $attr, ContainerBuilder $container): void
    {
        $fields = [
            [
                'class' => FkField::class,
                'translated' => false,
                'args' => [$entity . '_id', $entity . 'Id', $entity],
                'flags' => [
                    ['class' => PrimaryKey::class],
                    ['class' => Required::class]
                ]
            ],
            [
                'class' => FkField::class,
                'translated' => false,
                'args' => [$attr->entity . '_id', $attr->entity . 'Id', $attr->entity],
                'flags' => [
                    ['class' => PrimaryKey::class],
                    ['class' => Required::class]
                ]
            ],
            [
                'class' => ManyToOneAssociationField::class,
                'translated' => false,
                'args' => [$entity, $entity . '_id', $entity, 'id'],
                'flags' => []
            ],
            [
                'class' => ManyToOneAssociationField::class,
                'translated' => false,
                'args' => [$attr->entity, $attr->entity . '_id', $attr->entity, 'id'],
                'flags' => []
            ]
        ];

        $mapping = self::mapping($entity, $attr);

        $meta = [
            'entity_name' => $mapping,
            'fields' => $fields
        ];

        $definition = new Definition(AttributeMappingDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $container->setDefinition($mapping . '.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $registry->addMethodCall('register', [new Reference($mapping . '.definition'), $mapping . '.definition']);
    }
}
