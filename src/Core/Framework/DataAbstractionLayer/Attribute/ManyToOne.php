<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type ManyToOneData array{entity: string, onDelete: OnDelete, ref: string, api: bool|array{admin-api: bool, store-api: bool}, column: string|null, nullable: bool, type: string, translated: bool}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ManyToOne extends Field
{
    public const TYPE = 'many-to-one';

    public function __construct(
        public string $entity,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public string $ref = 'id',
        public bool|array $api = false,
        public ?string $column = null,
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }

    public static function fromArray(array $data): ManyToOne
    {
        $onDelete = $data['onDelete'] instanceof OnDelete
            ? $data['onDelete']
            : OnDelete::from((string) $data['onDelete']);

        $instance = new ManyToOne(
            (string) $data['entity'],
            $onDelete,
            (string) $data['ref'],
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
                'onDelete' => $this->onDelete->value,
                'ref' => $this->ref,
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
        $fk = $this->column ?? ($column . '_id');

        return new ManyToOneAssociationField(
            $propertyName,
            $fk,
            $this->entity,
            $this->ref,
        );
    }

    public function getFieldClass(): string
    {
        return ManyToOneAssociationField::class;
    }
}
