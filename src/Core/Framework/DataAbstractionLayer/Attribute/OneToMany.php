<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type OneToManyData array{entity: string, ref: string, onDelete: OnDelete, api: bool|array{admin-api: bool, store-api: bool}, nullable: bool, type: string, translated: bool}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OneToMany extends Field
{
    public const TYPE = 'one-to-many';

    public function __construct(
        public string $entity,
        public string $ref,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public bool|array $api = false
    ) {
        parent::__construct(type: self::TYPE, api: $api);
    }

    public static function fromArray(array $data): OneToMany
    {
        $onDelete = $data['onDelete'] instanceof OnDelete
            ? $data['onDelete']
            : OnDelete::from((string) $data['onDelete']);

        $instance = new OneToMany(
            (string) $data['entity'],
            (string) $data['ref'],
            $onDelete,
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
                'ref' => $this->ref,
                'onDelete' => $this->onDelete->value,
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
        return new OneToManyAssociationField(
            $propertyName,
            $this->entity,
            $this->ref,
            'id',
        );
    }

    public function getFieldClass(): string
    {
        return OneToManyAssociationField::class;
    }
}
