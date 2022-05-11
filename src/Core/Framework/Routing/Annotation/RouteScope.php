<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Use route defaults with "_routeScope". Example: @Route(defaults={"_routeScope"={"storefront"}})
 * @Annotation
 *
 * @Attributes({
 *   @Attribute("scopes",  type = "array"),
 * })
 */
class RouteScope extends ConfigurationAnnotation
{
    /**
     * @var array
     */
    private $scopes;

    /**
     * @return string
     */
    public function getAliasName()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_route_scope"={"storefront"})"')
        );

        return 'routeScope';
    }

    /**
     * @return bool
     */
    public function allowArray()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_route_scope"={"storefront"})"')
        );

        return false;
    }

    public function getScopes(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_route_scope"={"storefront"})"')
        );

        return $this->scopes;
    }

    public function setScopes(array $scopes): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_route_scope"={"storefront"})"')
        );

        $this->scopes = $scopes;
    }

    public function hasScope(string $scopeName): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_route_scope"={"storefront"})"')
        );

        return \in_array($scopeName, $this->scopes, true);
    }
}
