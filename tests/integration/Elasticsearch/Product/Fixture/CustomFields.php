<?php

declare(strict_types=1);

use Shopware\Core\System\CustomField\CustomFieldTypes;

return [
    [
        'name' => 'a',
        'type' => CustomFieldTypes::TEXT,
    ],
    [
        'name' => 'b',
        'type' => CustomFieldTypes::TEXT,
    ],
    [
        'name' => 'c',
        'type' => CustomFieldTypes::TEXT,
    ],
    [
        'name' => 'test_int',
        'type' => CustomFieldTypes::INT,
    ],
    [
        'name' => 'testFloatingField',
        'type' => CustomFieldTypes::FLOAT,
    ],
    [
        'name' => 'testField',
        'type' => CustomFieldTypes::TEXT,
    ],
    [
        'name' => 'test_select',
        'type' => CustomFieldTypes::SELECT,
    ],
    [
        'name' => 'test_text',
        'type' => CustomFieldTypes::TEXT,
    ],
    [
        'name' => 'test_html',
        'type' => CustomFieldTypes::HTML,
    ],
    [
        'name' => 'test_date',
        'type' => CustomFieldTypes::DATETIME,
    ],
    [
        'name' => 'test_object',
        'type' => CustomFieldTypes::JSON,
    ],
    [
        'name' => 'test_float',
        'type' => CustomFieldTypes::FLOAT,
    ],
    [
        'name' => 'test_bool',
        'type' => CustomFieldTypes::BOOL,
    ],
    [
        'name' => 'test_unmapped',
        'type' => 'unknown_type',
    ],
];
