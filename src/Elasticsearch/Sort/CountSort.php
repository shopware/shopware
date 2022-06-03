<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Sort;

use ONGR\ElasticsearchDSL\Sort\FieldSort;

class CountSort extends FieldSort
{
    public function __construct($field, $order = null, $params = [])
    {
        $path = explode('.', $field);
        array_pop($path);

        $params = array_merge(
            $params,
            [
                'mode' => 'sum',
                'nested' => ['path' => implode('.', $path)],
                'missing' => 0,
            ]
        );

        $path[] = '_count';

        parent::__construct(implode('.', $path), $order, $params);
    }
}
