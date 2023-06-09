<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ElasticsearchProductException extends HttpException
{
    public const ES_PRODUCT_CONFIG_NOT_FOUND = 'ELASTICSEARCH_PRODUCT__CONFIGURATION_NOT_FOUND';

    public static function configNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ES_PRODUCT_CONFIG_NOT_FOUND,
            'Configuration for product elasticsearch definition not found',
        );
    }
}
