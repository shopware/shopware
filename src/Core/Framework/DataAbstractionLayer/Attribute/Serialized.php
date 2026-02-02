<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SerializedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type SerializedData array{serializer: class-string<FieldSerializerInterface>, api: bool|array{admin-api: bool, store-api: bool}, translated: bool, column: string|null, nullable: bool, type: string}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Serialized extends Field
{
    public const TYPE = 'serialized';

    /**
     * @param class-string<FieldSerializerInterface> $serializer
     */
    public function __construct(
        public string $serializer = StringFieldSerializer::class,
        public bool|array $api = false,
        public bool $translated = false,
        public ?string $column = null
    ) {
        parent::__construct(type: self::TYPE, translated: $translated, api: $api, column: $column);
    }

    public static function fromArray(array $data): Serialized
    {
        /** @var class-string<FieldSerializerInterface> $serializer */
        $serializer = (string) $data['serializer'];

        $instance = new Serialized(
            $serializer,
            $data['api'],
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
                'serializer' => $this->serializer,
                'api' => $this->api,
                'translated' => $this->translated,
                'column' => $this->column,
                'nullable' => $this->nullable,
                'type' => $this->type,
            ],
        ]);

        return $definition;
    }

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        return new SerializedField($this->column ?? $column, $propertyName, $this->serializer);
    }

    public function getFieldClass(): string
    {
        return SerializedField::class;
    }
}
