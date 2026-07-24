<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Exception;

use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class SeoUrlRouteConfigException extends SeoException
{
    public const ROUTE_CONFIG_MISSING_PARAMETER_KEY_FOR_PRIMARY_KEY = 'FRAMEWORK__ROUTE_CONFIG_MISSING_PARAMETER_KEY_FOR_PRIMARY_KEY';
    public const ROUTE_CONFIG_NOT_FOUND_FOR_ENTITY_NAME = 'FRAMEWORK__ROUTE_CONFIG_NOT_FOUND_FOR_ENTITY_NAME';

    public static function routeConfigMissingParameterKeyForPrimaryKey(string $entityName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROUTE_CONFIG_MISSING_PARAMETER_KEY_FOR_PRIMARY_KEY,
            'Missing parameter key for primary key in route config of entity "{{ entityName }}".',
            ['entityName' => $entityName]
        );
    }

    public static function routeConfigNotFoundForEntityName(string $entityName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROUTE_CONFIG_NOT_FOUND_FOR_ENTITY_NAME,
            'No route config found for given entity name "{{ entityName }}".',
            ['entityName' => $entityName]
        );
    }
}
