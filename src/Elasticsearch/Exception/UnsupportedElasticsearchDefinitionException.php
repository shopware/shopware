<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use ElasticsearchException::unsupportedElasticsearchDefinition instead
 */
#[Package('core')]
class UnsupportedElasticsearchDefinitionException extends ElasticsearchException
{
    final public const CODE = 'ELASTICSEARCH_UNSUPPORTED_DEFINITION';

    public function __construct(string $entity)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ElasticsearchException::unsupportedElasticsearchDefinition instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::CODE,
            sprintf('Entity %s is not supported for elastic search', $entity)
        );
    }
}
