<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type ForeignKeyData array{entity: string, api: bool|array{admin-api: bool, store-api: bool}, column: string|null, nullable: bool, type: string, translated: bool}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ForeignKey extends Field
{
    public const TYPE = 'fk';

    /**
     * BC: Redeclared without default to require explicit initialization (Field originally had no default).
     */
    public bool $nullable;

    public function __construct(
        public string $entity,
        public bool|array $api = false,
        public ?string $column = null
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }

    public static function fromArray(array $data): ForeignKey
    {
        $instance = new ForeignKey(
            (string) $data['entity'],
            $data['api'],
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
                'api' => $this->api,
                'column' => $this->column,
                'nullable' => $this->nullable,
                'type' => $this->type,
                'translated' => $this->translated,
            ],
        ]);

        return $definition;
    }

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        return new FkField(
            $this->column ?? $column,
            $propertyName,
            $this->entity,
        );
    }

    public function getFieldClass(): string
    {
        return FkField::class;
    }
}
