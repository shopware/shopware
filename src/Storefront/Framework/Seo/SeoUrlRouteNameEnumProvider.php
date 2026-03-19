<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class SeoUrlRouteNameEnumProvider implements FieldEnumProviderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry
    ) {
    }

    public function isSupported(string $entity, string $fieldName): bool
    {
        return $entity === SeoUrlDefinition::ENTITY_NAME && $fieldName === 'routeName';
    }

    /**
     * {@inheritDoc}
     */
    public function getChoices(): array
    {
        $values = [];

        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $routeName => $_route) {
            $values[] = (string) $routeName;
        }

        return array_values(array_unique($values));
    }
}
