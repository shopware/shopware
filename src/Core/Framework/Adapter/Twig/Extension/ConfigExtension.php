<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\Twig\TwigContextHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
class ConfigExtension extends AbstractExtension
{
    /**
     * Static storefront template config values that were historically available
     * through config(), but are not persisted in SystemConfig.
     *
     * @var array<string, int|string|bool>
     */
    // @deprecated tag:v6.8.0 - Static template config values will be removed.
    private const STATIC_CONFIG_VALUES = [
        'seo.descriptionMaxLength' => 255,
        'cms.revocationNoticeCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
        'cms.taxCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
        'cms.tosCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
        'confirm.revocationNotice' => true,
    ];

    /**
     * @var array<string, string>
     */
    // @deprecated tag:v6.8.0 - Static template config values will be removed.
    private const STATIC_CONFIG_REPLACEMENTS = [
        'seo.descriptionMaxLength' => 'Use a template-local value instead.',
        'cms.revocationNoticeCmsPageId' => 'Use core.basicInformation.revocationPage instead.',
        'cms.taxCmsPageId' => 'No direct replacement exists.',
        'cms.tosCmsPageId' => 'Use core.basicInformation.tosPage instead.',
        'confirm.revocationNotice' => 'No direct replacement exists.',
    ];

    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('config', $this->config(...), ['needs_context' => true]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return string|bool|array<mixed>|float|int|null
     */
    public function config(array $context, string $key)
    {
        if (\array_key_exists($key, self::STATIC_CONFIG_VALUES)) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf(
                    'Reading the static template config "%s" through config() is deprecated and will be removed. %s',
                    $key,
                    self::STATIC_CONFIG_REPLACEMENTS[$key]
                )
            );

            return self::STATIC_CONFIG_VALUES[$key];
        }

        return $this->systemConfigService->get($key, $this->getSalesChannelId($context));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getSalesChannelId(array $context): ?string
    {
        $salesChannelContext = TwigContextHelper::getSalesChannelContext($context);
        if ($salesChannelContext instanceof SalesChannelContext) {
            return $salesChannelContext->getSalesChannelId();
        }

        $salesChannel = $context['salesChannel'] ?? null;
        if ($salesChannel instanceof SalesChannelEntity) {
            return $salesChannel->getId();
        }

        return null;
    }
}
