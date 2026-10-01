<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * Use `#[Serialized]` to configure a custom field serializer. Note: for the default serializer, you should use the proper fields instead.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Serialized extends Field
{
    public const TYPE = 'serialized';

    /**
     * @param class-string<FieldSerializerInterface> $serializer
     *
     * @deprecated tag:v6.8.0 - parameter $serializer will be required, as default serializer does not work
     */
    public function __construct(
        public string $serializer = StringFieldSerializer::class,
        public bool|array $api = false,
        public bool $translated = false,
        public ?string $column = null
    ) {
        if ($serializer === StringFieldSerializer::class) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                '$serializer parameter in `#[Serialized]` will be required in v6.8.0.0, as default serializer does not work.'
            );
        }

        parent::__construct(type: self::TYPE, translated: $translated, api: $api, column: $column);
    }
}
