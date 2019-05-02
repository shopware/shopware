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

    public function __construct(string $storageName, string $propertyName, ?string $fieldType = null)
    {
        parent::__construct($storageName, $propertyName);
        $this->fieldType = $fieldType;
    }

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    protected function getSerializerClass(): string
    {
        return ListFieldSerializer::class;
    }
}
