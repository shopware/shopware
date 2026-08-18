<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearch\Common\Exceptions\BadRequest400Exception;
use OpenSearch\Exception\BadRequestHttpException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ElasticsearchProductException extends HttpException
{
    public const ES_PRODUCT_CONFIG_NOT_FOUND = 'ELASTICSEARCH_PRODUCT__CONFIGURATION_NOT_FOUND';
    public const ES_PRODUCT_CANNOT_CHANGE_CUSTOM_FIELD_TYPE = 'ELASTICSEARCH_PRODUCT__CANNOT_CHANGE_CUSTOM_FIELD_TYPE';
    public const ES_PRODUCT_CANNOT_CHANGE_FIELD_TYPE = 'ELASTICSEARCH_PRODUCT__CANNOT_CHANGE_FIELD_TYPE';

    public static function configNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ES_PRODUCT_CONFIG_NOT_FOUND,
            'Configuration for product elasticsearch definition not found',
        );
    }

    /**
     * @deprecated tag:v6.8.0 - $previous will only accept BadRequestHttpException
     */
    public static function cannotChangeFieldType(BadRequest400Exception|BadRequestHttpException $previous): self
    {
        if ($previous instanceof BadRequest400Exception) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf('Passing %s to %s is deprecated and support will be removed in v6.8.0.0. Please pass an instance of %s instead.', BadRequest400Exception::class, __METHOD__, BadRequestHttpException::class)
            );
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ES_PRODUCT_CANNOT_CHANGE_FIELD_TYPE,
            'One or more fields already exist in the index with different types. Please reset the index and rebuild it.',
            [],
            $previous,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - $previous will only accept BadRequestHttpException
     */
    public static function cannotChangeCustomFieldType(BadRequest400Exception|BadRequestHttpException $previous): self
    {
        if ($previous instanceof BadRequest400Exception) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf('Passing %s to %s is deprecated and support will be removed in v6.8.0.0. Please pass an instance of %s instead.', BadRequest400Exception::class, __METHOD__, BadRequestHttpException::class)
            );
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ES_PRODUCT_CANNOT_CHANGE_CUSTOM_FIELD_TYPE,
            'One or more custom fields already exist in the index with different types. Please reset the index and rebuild it.',
            [],
            $previous,
        );
    }
}
