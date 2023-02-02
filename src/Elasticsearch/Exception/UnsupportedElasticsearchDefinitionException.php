<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class UnsupportedElasticsearchDefinitionException extends ShopwareHttpException
{
    public const CODE = 'ELASTICSEARCH_UNSUPPORTED_DEFINITION';

    public function __construct(string $entity)
    {
        parent::__construct(sprintf('Entity %s is not supported for elastic search', $entity));
    }

    public function getErrorCode(): string
    {
        return self::CODE;
    }
}
