<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type OneToOneData array{entity: string, column: string|null, onDelete: OnDelete, ref: string, api: bool|array{admin-api: bool, store-api: bool}, nullable: bool, type: string, translated: bool}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OneToOne extends Field
{
    public const TYPE = 'one-to-one';

    public function __construct(
        public string $entity,
        public ?string $column = null,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public string $ref = 'id',
        public bool|array $api = false
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }

    public static function fromArray(array $data): OneToOne
    {
        $onDelete = $data['onDelete'] instanceof OnDelete
            ? $data['onDelete']
            : OnDelete::from((string) $data['onDelete']);

        $instance = new OneToOne(
            (string) $data['entity'],
            $data['column'],
            $onDelete,
            (string) $data['ref'],
            $data['api']
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
                'onDelete' => $this->onDelete->value,
                'ref' => $this->ref,
                'api' => $this->api,
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

        return new OneToOneAssociationField(
            $propertyName,
            $fk,
            $this->ref,
            $this->entity,
            false,
        );
    }

    public function getFieldClass(): string
    {
        return OneToOneAssociationField::class;
    }
}
