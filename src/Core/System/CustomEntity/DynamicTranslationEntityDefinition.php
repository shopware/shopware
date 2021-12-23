<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal Used for custom entities
 */
class DynamicTranslationEntityDefinition extends EntityTranslationDefinition
{
    protected string $root;

    protected array $fieldDefinitions;

    protected ContainerInterface $container;

    protected function getParentDefinitionEntity(): string
    {
        return $this->root;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return null;
    }

    public static function create(string $root, array $fields, ContainerInterface $container): DynamicTranslationEntityDefinition
    {
        $self = new self();
        $self->root = $root;
        $self->fieldDefinitions = $fields;
        $self->container = $container;

        return $self;
    }

    public function getEntityName(): string
    {
        return $this->root . '_translation';
    }

    protected function defineFields(): FieldCollection
    {
        return DynamicFieldFactory::create($this->container, $this->getEntityName(), $this->fieldDefinitions);
    }

    protected static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }
}
