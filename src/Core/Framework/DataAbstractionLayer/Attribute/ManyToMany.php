<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type ManyToManyData array{entity: string, onDelete: OnDelete, api: bool|array{admin-api: bool, store-api: bool}, mapping: string|null, nullable: bool, type: string, translated: bool}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ManyToMany extends Field
{
    public const TYPE = 'many-to-many';

    public function __construct(
        public string $entity,
        public OnDelete $onDelete = OnDelete::NO_ACTION,
        public bool|array $api = false,
        public ?string $mapping = null
    ) {
        parent::__construct(type: self::TYPE, api: $api);
    }

    public static function fromArray(array $data): ManyToMany
    {
        $onDelete = $data['onDelete'] instanceof OnDelete
            ? $data['onDelete']
            : OnDelete::from((string) $data['onDelete']);

        $instance = new ManyToMany(
            (string) $data['entity'],
            $onDelete,
            $data['api'],
            $data['mapping']
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
                'api' => $this->api,
                'mapping' => $this->mapping,
                'nullable' => $this->nullable,
                'type' => $this->type,
                'translated' => $this->translated,
            ],
        ]);

        return $definition;
    }

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        $mappingName = $this->getMappingName($entityName);

        return new ManyToManyAssociationField(
            $propertyName,
            $this->entity,
            $mappingName,
            $entityName . '_id',
            $this->entity . '_id',
        );
    }

    public function getFieldClass(): string
    {
        return ManyToManyAssociationField::class;
    }

    /**
     * Falls back to alphabetically sorted "{entity}_{entity}" if no explicit mapping provided.
     */
    public function getMappingName(string $entityName): string
    {
        if ($this->mapping !== null) {
            return $this->mapping;
        }

        $items = [$entityName, $this->entity];
        sort($items);

        return implode('_', $items);
    }
}
