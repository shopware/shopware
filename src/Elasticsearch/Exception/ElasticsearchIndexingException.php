<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class ElasticsearchIndexingException extends ShopwareHttpException
{
    public const CODE = 'ELASTICSEARCH_INDEXING';

    public function __construct(array $items, ?\Throwable $previous = null)
    {
        $message = \PHP_EOL . implode(\PHP_EOL . '#', array_column($items, 'reason'));

        parent::__construct(
            sprintf('Following errors occurred while indexing: %s', $message),
            [],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return self::CODE;
    }
}
