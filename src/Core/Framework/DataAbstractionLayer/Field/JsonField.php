<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class JsonField extends Field implements StorageAware
{
    protected string $storageName;

    /**
     * @var list<Field>
     */
    protected array $propertyMapping;

    /**
     * @var array<mixed>|null
     */
    protected ?array $default;

    /**
     * @param list<Field> $propertyMapping
     * @param array<mixed>|null $default
     */
    public function __construct(
        string $storageName,
        string $propertyName,
        array $propertyMapping = [],
        ?array $default = null
    ) {
        $this->storageName = $storageName;
        $this->propertyMapping = $propertyMapping;
        $this->default = $default;
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return list<Field>
     */
    public function getPropertyMapping(): array
    {
        return $this->propertyMapping;
    }

    /**
     * @return array<mixed>|null
     */
    public function getDefault(): ?array
    {
        return $this->default;
    }

    protected function getSerializerClass(): string
    {
        return JsonFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return JsonFieldAccessorBuilder::class;
    }
}
