<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Sort;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Sort\FieldSort;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CountSort extends FieldSort
{
    /**
     * @param array<mixed> $params
     */
    public function __construct(
        string $field,
        ?string $order = null,
        ?BuilderInterface $nestedFilter = null,
        array $params = []
    ) {
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

        parent::__construct(implode('.', $path), $order, $nestedFilter, $params);
    }
}
