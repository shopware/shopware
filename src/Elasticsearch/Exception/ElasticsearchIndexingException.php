<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use ElasticsearchException::indexingError instead
 */
#[Package('core')]
class ElasticsearchIndexingException extends ElasticsearchException
{
    final public const CODE = 'ELASTICSEARCH_INDEXING';

    /**
     * @param array{reason: string}|array{reason: string}[] $items
     */
    public function __construct(array $items)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'ElasticsearchException::indexingError')
        );

        $message = \PHP_EOL . implode(\PHP_EOL . '#', array_column($items, 'reason'));

        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INDEXING_ERROR,
            'Following errors occurred while indexing: {{ messages }}',
            ['messages' => $message]
        );
    }
}
