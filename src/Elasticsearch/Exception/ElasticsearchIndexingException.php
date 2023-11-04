<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class ElasticsearchIndexingException extends ShopwareHttpException
{
    final public const CODE = 'ELASTICSEARCH_INDEXING';

    /**
     * @param array{reason: string}|array{reason: string}[] $items
     */
    public function __construct(array $items)
    {
        $message = \PHP_EOL . implode(\PHP_EOL . '#', array_column($items, 'reason'));

        parent::__construct(
            sprintf('Following errors occurred while indexing: %s', $message)
        );
    }

    public function getErrorCode(): string
    {
        return self::CODE;
    }
}
