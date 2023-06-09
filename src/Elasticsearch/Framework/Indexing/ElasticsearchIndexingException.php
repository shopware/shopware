<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException as IndexingError;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ElasticsearchIndexingException extends HttpException
{
    public const ES_DEFINITION_NOT_FOUND = 'ELASTICSEARCH_INDEXING__DEFINITION_NOT_FOUND';

    public const ES_INDEXING_ERROR = 'ELASTICSEARCH_INDEXING__INDEXING_ERROR';

    public static function definitionNotFound(string $definition): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ES_DEFINITION_NOT_FOUND,
            sprintf('Elasticsearch definition of %s not found', $definition),
        );
    }

    /**
     * @param array{index: string, id: string, type: string, reason: string}[] $errors
     */
    public static function indexingError(array $errors): ShopwareHttpException
    {
        return new IndexingError($errors);
    }
}
