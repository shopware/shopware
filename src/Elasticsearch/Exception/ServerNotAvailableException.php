<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use ElasticsearchException::serverNotAvailable instead
 */
#[Package('core')]
class ServerNotAvailableException extends ElasticsearchException
{
    final public const CODE = 'ELASTICSEARCH_SERVER_NOT_AVAILABLE';

    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use ElasticsearchException::serverNotAvailable instead')
        );

        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CODE,
            'Elasticsearch server is not available'
        );
    }
}
