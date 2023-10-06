<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('core')]
class SeoUrlFunctionExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RoutingExtension $routingExtension,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seoUrl', $this->seoUrl(...), ['is_safe_callback' => $this->routingExtension->isUrlGenerationSafe(...)]),
        ];
    }

    public function seoUrl(string $name, array $parameters = []): string
    {
        return $this->seoUrlReplacer->generate($name, $parameters);
    }
}
