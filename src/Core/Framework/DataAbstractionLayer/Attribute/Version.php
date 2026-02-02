<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type VersionData array{nullable: bool, type: string, translated: bool, api: bool|array{admin-api: bool, store-api: bool}, column: string|null}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Version extends Field
{
    public const TYPE = 'version';

    public function __construct()
    {
        parent::__construct(type: self::TYPE, api: true);
    }

    public static function fromArray(array $data): Version
    {
        $instance = new Version();
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
        return new VersionField();
    }

    public function getFieldClass(): string
    {
        return VersionField::class;
    }
}
