<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;

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
     * @deprecated tag:v6.5.0 Return always true, since the property gets removed
     * @deprecated tag:v6.6.0 Remove the method completely
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * Enable strict mode which forces the decode to return non-associative array. (json_encode will encode it as an array instead of an object)
     *
     * @deprecated tag:v6.5.0 Will be changed to a noop. All `ListField`s will be strict and this will just return $this
     * @deprecated tag:v6.6.0 Remove the method completely
     */
    public function setStrict(bool $strict): ListField
    {
        $this->strict = $strict;

        return $this;
    }

    protected function getSerializerClass(): string
    {
        return ListFieldSerializer::class;
    }
}
