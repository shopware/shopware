<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopware\Core\Framework\Feature;

/**
 * Stores a JSON formatted value list. This can be typed using the third constructor parameter.
 *
 * Definition example:
 *
 *      // allow every type
 *      new ListField('product_ids', 'productIds');
 *
 *      // allow int types only
 *      new ListField('product_ids', 'productIds', IntField::class);
 *
 * Output in database:
 *
 *      // mixed type value
 *      ['this is a string', 'another string', true, 15]
 *
 *      // single type values
 *      [12,55,192,22]
 */
class ListField extends JsonField
{
    /**
     * @var string|null
     */
    private $fieldType;

    /**
     * @deprecated tag:v6.5.0 Property strict will be removed
     *
     * @var bool
     */
    private $strict = false;

    public function __construct(string $storageName, string $propertyName, ?string $fieldType = null)
    {
        parent::__construct($storageName, $propertyName);
        $this->fieldType = $fieldType;
    }

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    /**
     * Strict `ListField` does not support keys and will strip them on decode. Use `JsonField` instead.
     *
     * @deprecated tag:v6.5.0 - will be removed
     */
    public function isStrict(): bool
    {
        if (!$this->strict) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'JsonField')
            );
        }

        return $this->strict;
    }

    /**
     * Enable strict mode which forces the decode to return non-associative array. (json_encode will encode it as an array instead of an object)
     *
     * @deprecated tag:v6.5.0 - will be removed
     */
    public function setStrict(bool $strict): ListField
    {
        if (!$strict) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'JsonField')
            );
        }

        $this->strict = $strict;

        return $this;
    }

    protected function getSerializerClass(): string
    {
        return ListFieldSerializer::class;
    }
}
