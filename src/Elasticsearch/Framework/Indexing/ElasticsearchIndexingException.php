<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
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
    public static function indexingError(array $errors): self
    {
        $message = \PHP_EOL . implode(\PHP_EOL . '#', array_column($errors, 'reason'));

        $message = sprintf('Following errors occurred while indexing: %s', $message);

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ES_INDEXING_ERROR,
            $message,
        );
    }
}
