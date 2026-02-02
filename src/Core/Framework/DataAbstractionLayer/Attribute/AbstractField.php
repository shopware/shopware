<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

#[Package('framework')]
abstract class AbstractField
{
    public bool $nullable = true;

    /**
     * @param bool|array{admin-api: bool, store-api: bool} $api
     */
    public function __construct(
        public string $type,
        public bool $translated = false,
        public bool|array $api = false,
        public ?string $column = null,
    ) {
    }

    /**
     * Used by Symfony container compilation via Definition factory pattern.
     *
     * @param array<string, mixed> $data
     */
    abstract public static function fromArray(array $data): AbstractField;

    /**
     * Enables serialization for Symfony's XmlDumper and PhpDumper during container compilation.
     */
    abstract public function toDefinition(): Definition;

    /**
     * @param string|null $propertyType PHP type class (e.g., enum) for type-specific field creation
     */
    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        $fieldClass = $this->getFieldClass();

        return new $fieldClass($this->column ?? $column, $propertyName);
    }

    /**
     * @return class-string<DalField>
     */
    abstract public function getFieldClass(): string;
}
