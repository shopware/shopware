<?php declare(strict_types=1);

namespace Scripts\Examples;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;

/**
 * @return array{json: ImportExportV2ProfileEntity, csv: ImportExportV2ProfileEntity}
 */
function buildProductImportExportV2Profiles(): array
{
    $recordPaths = [
        'productNumber',
        'active',
        'stock',
        'tax.id',
        'translations.DEFAULT.name',
        'tags.*.id',
        'tags.*.name',
    ];

    $filters = [
        [
            'type' => 'multi',
            'operator' => 'AND',
            'queries' => [
                [
                    'type' => 'equals',
                    'field' => 'active',
                    'value' => true,
                ],
                [
                    'type' => 'equals',
                    'field' => 'parentId',
                    'value' => null,
                ],
            ],
        ],
    ];

    $jsonProfile = new ImportExportV2ProfileEntity();
    $jsonProfile->setTechnicalName('product-json');
    $jsonProfile->setEntity('product');
    $jsonProfile->setFormat('json');
    $jsonProfile->setFilters($filters);
    $jsonProfile->setRecordPaths($recordPaths);
    $jsonProfile->setMatchBy('productNumber');

    $csvProfile = new ImportExportV2ProfileEntity();
    $csvProfile->setTechnicalName('product-csv');
    $csvProfile->setEntity('product');
    $csvProfile->setFormat('csv');
    $csvProfile->setFilters($filters);
    $csvProfile->setRecordPaths($recordPaths);
    $csvProfile->setMatchBy('productNumber');
    $csvProfile->setFieldMappings([
        [
            'column' => 'product_number',
            'path' => 'productNumber',
        ],
        [
            'column' => 'active',
            'path' => 'active',
            'type' => 'bool',
        ],
        [
            'column' => 'stock',
            'path' => 'stock',
            'type' => 'int',
        ],
        [
            'column' => 'tax_id',
            'path' => 'tax.id',
        ],
        [
            'column' => 'name',
            'path' => 'translations.DEFAULT.name',
        ],
        [
            'column' => 'tag_ids',
            'path' => 'tags.*.id',
            'separator' => '|',
        ],
        [
            'column' => 'tags',
            'path' => 'tags.*.name',
            'separator' => '|',
        ],
    ]);

    return [
        'json' => $jsonProfile,
        'csv' => $csvProfile,
    ];
}
