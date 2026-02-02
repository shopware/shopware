<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type AutoIncrementData array{nullable: bool, type: string, translated: bool, api: bool|array{admin-api: bool, store-api: bool}, column: string|null}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AutoIncrement extends Field
{
    public const TYPE = 'auto-increment';

    public function __construct()
    {
        parent::__construct(type: self::TYPE, api: true);
    }

    public static function fromArray(array $data): AutoIncrement
    {
        $instance = new AutoIncrement();
        $instance->nullable = (bool) $data['nullable'];
        $instance->column = $data['column'];

        return $instance;
    }

    public function toDefinition(): Definition
    {
        $definition = new Definition(self::class);
        $definition->setFactory([self::class, 'fromArray']);
        $definition->setArguments([
            [
                'nullable' => $this->nullable,
                'type' => $this->type,
                'translated' => $this->translated,
                'api' => $this->api,
                'column' => $this->column,
            ],
        ]);

        return $definition;
    }

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        return new AutoIncrementField();
    }

    public function getFieldClass(): string
    {
        return AutoIncrementField::class;
    }
}
