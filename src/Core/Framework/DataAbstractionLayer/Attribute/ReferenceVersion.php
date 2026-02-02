<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type ReferenceVersionData array{entity: string, column: string|null, nullable: bool, type: string, translated: bool, api: bool|array{admin-api: bool, store-api: bool}}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ReferenceVersion extends Field
{
    public const TYPE = 'reference-version';

    public function __construct(public string $entity, public ?string $column = null)
    {
        parent::__construct(type: self::TYPE, api: true, column: $column);
    }

    public static function fromArray(array $data): ReferenceVersion
    {
        $instance = new ReferenceVersion(
            (string) $data['entity'],
            $data['column']
        );
        $instance->nullable = (bool) $data['nullable'];

        return $instance;
    }

    public function toDefinition(): Definition
    {
        $definition = new Definition(self::class);
        $definition->setFactory([self::class, 'fromArray']);
        $definition->setArguments([
            [
                'entity' => $this->entity,
                'column' => $this->column,
                'nullable' => $this->nullable,
                'type' => $this->type,
                'translated' => $this->translated,
                'api' => $this->api,
            ],
        ]);

        return $definition;
    }

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        return new ReferenceVersionField($this->entity, $this->column ?? $column);
    }

    public function getFieldClass(): string
    {
        return ReferenceVersionField::class;
    }
}
