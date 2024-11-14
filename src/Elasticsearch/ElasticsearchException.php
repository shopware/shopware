<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ElasticsearchException extends HttpException
{
    public const DEFINITION_NOT_FOUND = 'ELASTICSEARCH__DEFINITION_NOT_FOUND';
    public const UNSUPPORTED_DEFINITION = 'ELASTICSEARCH__UNSUPPORTED_DEFINITION';
    public const INDEXING_ERROR = 'ELASTICSEARCH__INDEXING_ERROR';
    public const NESTED_AGGREGATION_MISSING = 'ELASTICSEARCH__NESTED_FILTER_AGGREGATION_MISSING';
    public const UNSUPPORTED_AGGREGATION = 'ELASTICSEARCH__UNSUPPORTED_AGGREGATION';
    public const UNSUPPORTED_FILTER = 'ELASTICSEARCH__UNSUPPORTED_FILTER';
    public const NESTED_AGGREGATION_PARSE_ERROR = 'ELASTICSEARCH__NESTED_AGGREGATION_PARSE_ERROR';
    public const PARENT_FILTER_ERROR = 'ELASTICSEARCH__PARENT_FILTER_ERROR';
    public const SERVER_NOT_AVAILABLE = 'ELASTICSEARCH__SERVER_NOT_AVAILABLE';

    public const EMPTY_QUERY = 'ELASTICSEARCH__EMPTY_QUERY';

    public const AWS_CREDENTIALS_NOT_FOUND = 'ELASTICSEARCH__AWS_CREDENTIALS_NOT_FOUND';

    public static function definitionNotFound(string $definition): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DEFINITION_NOT_FOUND,
            'Definition {{ definition }} not found',
            ['definition' => $definition]
        );
    }

    public static function unsupportedElasticsearchDefinition(string $definition): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_DEFINITION,
            'Definition {{ definition }} is not supported for elasticsearch',
            ['definition' => $definition]
        );
    }

    /**
     * @param array{reason: string}|array{reason: string}[] $items
     */
    public static function indexingError(array $items): self
    {
        $esErrors = \PHP_EOL . implode(\PHP_EOL, array_column($items, 'reason'));

        $exceptionMessage = 'Following errors occurred while indexing: {{ messages }}';
        if (\in_array('mapper_parsing_exception', array_column($items, 'type'), true)) {
            $exceptionMessage = 'Some fields are mapped to incorrect types. Please reset the index and rebuild it. Full errors: {{ messages }}';
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INDEXING_ERROR,
            $exceptionMessage,
            ['messages' => $esErrors]
        );
    }

    public static function nestedAggregationMissingInFilterAggregation(string $aggregation): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NESTED_AGGREGATION_MISSING,
            'Filter aggregation {{ aggregation }} contains no nested aggregation.',
            ['aggregation' => $aggregation]
        );
    }

    public static function unsupportedAggregation(string $aggregationClass): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_AGGREGATION,
            'Provided aggregation of class {{ aggregationClass }} is not supported',
            ['aggregationClass' => $aggregationClass]
        );
    }

    public static function unsupportedFilter(string $filterClass): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_FILTER,
            'Provided filter of class {{ filterClass }} is not supported',
            ['filterClass' => $filterClass]
        );
    }

    public static function nestedAggregationParseError(string $aggregationName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NESTED_AGGREGATION_PARSE_ERROR,
            'Nested filter aggregation {{ aggregation }} can not be parsed.',
            ['aggregation' => $aggregationName]
        );
    }

    public static function parentFilterError(string $filter): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PARENT_FILTER_ERROR,
            'Expected nested+filter+reverse pattern for parsed filter {{ filter }} to set next parent correctly.',
            ['filter' => $filter]
        );
    }

    public static function serverNotAvailable(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SERVER_NOT_AVAILABLE,
            'Elasticsearch server is not available'
        );
    }

    public static function emptyQuery(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::EMPTY_QUERY,
            'Empty query provided'
        );
    }

    public static function awsCredentialsNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::AWS_CREDENTIALS_NOT_FOUND,
            'Could not get AWS credentials'
        );
    }
}
