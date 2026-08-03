<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Framework\Adapter\Twig\TwigContextHelper;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
class EntitySeoUrlFunctionExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityRouteResolver $entityRouteResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('entitySeoUrl', $this->entitySeoUrl(...), [
                'needs_context' => true,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function entitySeoUrl(array $context, string $name, string $primaryKey): string
    {
        return $this->entityRouteResolver->generateSeoUrlPlaceholder(
            $name,
            $primaryKey,
            $this->getSalesChannelTypeId($context)
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getSalesChannelTypeId(array $context): ?string
    {
        return TwigContextHelper::getSalesChannelContext($context)?->getSalesChannel()->getTypeId();
    }
}
