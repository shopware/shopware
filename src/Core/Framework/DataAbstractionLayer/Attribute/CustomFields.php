<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields as CustomFieldsField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type CustomFieldsData array{translated: bool, column: string|null, nullable: bool, type: string, api: bool|array{admin-api: bool, store-api: bool}}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CustomFields extends Field
{
    public const TYPE = 'custom-fields';

    public function __construct(public bool $translated = false, public ?string $column = null)
    {
        parent::__construct(type: self::TYPE, translated: $this->translated, api: true, column: $column);
    }

    public static function fromArray(array $data): CustomFields
    {
        $instance = new CustomFields(
            (bool) $data['translated'],
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
                'translated' => $this->translated,
                'column' => $this->column,
                'nullable' => $this->nullable,
                'type' => $this->type,
                'api' => $this->api,
            ],
        ]);

        return $definition;
    }

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        return new CustomFieldsField($this->column ?? $column, $propertyName);
    }

    public function getFieldClass(): string
    {
        return CustomFieldsField::class;
    }
}
