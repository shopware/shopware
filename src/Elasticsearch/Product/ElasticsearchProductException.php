<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearch\Common\Exceptions\BadRequest400Exception;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ElasticsearchProductException extends HttpException
{
    public const ES_PRODUCT_CONFIG_NOT_FOUND = 'ELASTICSEARCH_PRODUCT__CONFIGURATION_NOT_FOUND';
    public const ES_PRODUCT_CANNOT_CHANGE_CUSTOM_FIELD_TYPE = 'ELASTICSEARCH_PRODUCT__CANNOT_CHANGE_CUSTOM_FIELD_TYPE';

    public static function configNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ES_PRODUCT_CONFIG_NOT_FOUND,
            'Configuration for product elasticsearch definition not found',
        );
    }

    public static function cannotChangeCustomFieldType(BadRequest400Exception $previous): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ES_PRODUCT_CANNOT_CHANGE_CUSTOM_FIELD_TYPE,
            'One or more custom fields already exist in the index with different types. Please reset the index and rebuild it.',
            [],
            $previous,
        );
    }
}
