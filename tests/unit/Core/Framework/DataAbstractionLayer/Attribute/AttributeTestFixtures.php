<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute;

/**
 * Standard test constants for attribute tests.
 *
 * @internal
 */
final class AttributeTestFixtures
{
    // Standard entity names
    public const ENTITY_NAME = 'test_entity';
    public const ENTITY_NAME_PRODUCT = 'product';
    public const ENTITY_NAME_ORDER = 'order';
    public const ENTITY_NAME_CATEGORY = 'category';
    public const ENTITY_NAME_CUSTOMER = 'customer';

    // Standard property names
    public const PROPERTY_NAME = 'testProperty';
    public const PROPERTY_NAME_ID = 'id';
    public const PROPERTY_NAME_CURRENCY = 'currency';
    public const PROPERTY_NAME_PRODUCT = 'product';
    public const PROPERTY_NAME_CATEGORIES = 'categories';
    public const PROPERTY_NAME_TAGS = 'tags';

    // Standard column names
    public const COLUMN_NAME = 'test_property';
    public const COLUMN_NAME_ID = 'id';
    public const COLUMN_NAME_CURRENCY_ID = 'currency_id';
    public const COLUMN_NAME_PRODUCT_ID = 'product_id';

    // Standard mapping table names
    public const MAPPING_NAME_CATEGORY_PRODUCT = 'category_product';
    public const MAPPING_NAME_PRODUCT_TAG = 'product_tag';

    // Standard enum classes
    public const STRING_ENUM_CLASS = TestStringEnum::class;
    public const INT_ENUM_CLASS = TestIntEnum::class;

    // Standard reference fields
    public const REFERENCE_FIELD_ID = 'id';
    public const REFERENCE_FIELD_UUID = 'uuid';
}

/**
 * Test string enum for attribute tests.
 *
 * @internal
 */
enum TestStringEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

/**
 * Test int enum for attribute tests.
 *
 * @internal
 */
enum TestIntEnum: int
{
    case FIRST = 1;
    case SECOND = 2;
}
