<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Exception;

use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class SeoUrlRouteConfigException extends SeoException
{
    public const ROUTE_PARAMETERS_MISMATCHING = 'FRAMEWORK__ROUTE_PARAMETERS_MISMATCHING';
    public const ROUTE_CONFIG_NOT_FOUND_FOR_ENTITY_NAME = 'FRAMEWORK__ROUTE_CONFIG_NOT_FOUND_FOR_ENTITY_NAME';

    /**
     * @param list<string> $required
     * @param list<string> $given
     */
    public static function routeParametersMismatching(array $required, array $given): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROUTE_PARAMETERS_MISMATCHING,
            'Mismatch between required route parameters and given values.',
            ['required' => $required, 'given' => $given]
        );
    }

    public static function routeConfigNotFoundForEntityName(string $entityName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROUTE_CONFIG_NOT_FOUND_FOR_ENTITY_NAME,
            'No route config found for given entity name.',
            ['entityName' => $entityName]
        );
    }
}
