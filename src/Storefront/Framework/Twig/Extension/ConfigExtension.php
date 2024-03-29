<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Shopware\Storefront\Framework\Twig\TemplateConfigAccessor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('storefront')]
class ConfigExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly TemplateConfigAccessor $config)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('config', $this->config(...), ['needs_context' => true]),
            new TwigFunction('theme_config', $this->theme(...), ['needs_context' => true]),
            new TwigFunction('theme_scripts', $this->scripts(...), ['needs_context' => true]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return string|bool|array<mixed>|float|int|null
     */
    public function config(array $context, string $key)
    {
        return $this->config->config($key, $this->getSalesChannelId($context));
    }

    /**
     * @param array<string, SalesChannelContext|string> $context
     *
     * @return string|bool|array<string, mixed>|float|int|null
     */
    public function theme(array $context, string $key)
    {
        return $this->config->theme($key, $this->getContext($context), $this->getThemeId($context));
    }

    /**
     * @return array<int, string> $items
     */
    public function scripts(): array
    {
        return $this->config->scripts();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getSalesChannelId(array $context): ?string
    {
        if (isset($context['context'])) {
            $salesChannelContext = $context['context'];
            if ($salesChannelContext instanceof SalesChannelContext) {
                return $salesChannelContext->getSalesChannelId();
            }
        }
        if (isset($context['salesChannel'])) {
            $salesChannel = $context['salesChannel'];
            if ($salesChannel instanceof SalesChannelEntity) {
                return $salesChannel->getId();
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getThemeId(array $context): ?string
    {
        return $context['themeId'] ?? null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getContext(array $context): SalesChannelContext
    {
        if (!isset($context['context'])) {
            throw StorefrontFrameworkException::salesChannelContextObjectNotFound();
        }

        $context = $context['context'];

        if (!$context instanceof SalesChannelContext) {
            throw StorefrontFrameworkException::salesChannelContextObjectNotFound();
        }

        return $context;
    }
}
