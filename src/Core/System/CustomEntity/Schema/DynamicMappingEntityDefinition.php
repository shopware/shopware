<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal Used for custom entities
 */
#[Package('core')]
class DynamicMappingEntityDefinition extends MappingEntityDefinition
{
    protected string $name;

    protected array $fieldDefinitions;

    protected string $source;

    protected string $reference;

    public static function create(string $source, string $reference, string $name): DynamicMappingEntityDefinition
    {
        $self = new self();

        $self->name = $name;
        $self->source = $source;
        $self->reference = $reference;

        return $self;
    }

    public function getEntityName(): string
    {
        return $this->name;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new FkField($this->source . '_id', self::kebabCaseToCamelCase($this->source) . 'Id', $this->source, 'id'))
                ->addFlags(new Required(), new PrimaryKey()),

            (new FkField($this->reference . '_id', self::kebabCaseToCamelCase($this->reference) . 'Id', $this->reference, 'id'))
                ->addFlags(new Required(), new PrimaryKey()),

            (new ManyToOneAssociationField(self::kebabCaseToCamelCase($this->reference), $this->reference . '_id', $this->reference, 'id', false)),
            (new ManyToOneAssociationField(self::kebabCaseToCamelCase($this->source), $this->source . '_id', $this->source, 'id', false)),
        ]);

        $definition = $this->registry->getByEntityName($this->source);
        if ($definition->isVersionAware()) {
            $fields->add(
                (new ReferenceVersionField($definition->getEntityName()))->addFlags(new PrimaryKey(), new Required()),
            );
        }

        $definition = $this->registry->getByEntityName($this->reference);
        if ($definition->isVersionAware()) {
            $fields->add(
                (new ReferenceVersionField($definition->getEntityName()))->addFlags(new PrimaryKey(), new Required()),
            );
        }

        return $fields;
    }

    protected static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }
}
