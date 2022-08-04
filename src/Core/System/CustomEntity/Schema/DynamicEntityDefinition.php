<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal The use of this class is reserved for the custom_entity feature
 *
 * @package core
 */
class DynamicEntityDefinition extends EntityDefinition
{
    protected string $name;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $fieldDefinitions;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $flags;

    protected ContainerInterface $container;

    /**
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, array<string, mixed>> $flags
     */
    public static function create(
        string $name,
        array $fields,
        array $flags,
        ContainerInterface $container
    ): DynamicEntityDefinition {
        $self = new self();
        $self->name = $name;
        $self->fieldDefinitions = $fields;
        $self->container = $container;
        $self->flags = $flags;

        return $self;
    }

    public function getEntityName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, array<string,mixed>>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = DynamicFieldFactory::create($this->container, $this->getEntityName(), $this->fieldDefinitions);

        $collection->add(
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new Required(), new PrimaryKey()),
        );

        return $collection;
    }
}
