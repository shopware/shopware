<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService instead
 */
#[Package('framework')]
class ConfigurationService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly SystemConfigDefinitionService $systemConfigDefinitionService
    ) {
    }

    /**
     * @throws SystemConfigException
     * @throws \InvalidArgumentException
     * @throws BundleConfigNotFoundException
     * @throws UtilException when config.xml exists but contains invalid XML
     *
     * @return array<mixed>
     */
    public function getConfiguration(string $domain, Context $context): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', SystemConfigDefinitionService::class));

        $config = $this->systemConfigDefinitionService->getConfiguration($domain, $context);

        // collect all cards from the tabs into the config array to keep the legacy structure
        $config = array_values(array_reduce($config, fn (array $carry, SystemConfigTab $tab) => [
            ...$carry,
            ...array_map(static function (SystemConfigCard $card): array {
                $card = (array) $card;

                $card['elements'] = array_map(fn (SystemConfigElement $element): array => (array) $element, $card['elements']);

                return $card;
            }, $tab->cards),
        ], []));

        return $config;
    }

    /**
     * @return array<mixed>
     */
    public function getResolvedConfiguration(string $domain, Context $context, ?string $salesChannelId = null): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', SystemConfigDefinitionService::class));

        $config = [];

        if ($this->checkConfiguration($domain, $context)) {
            $config = array_merge(
                $config,
                $this->enrichValues(
                    $this->getConfiguration($domain, $context),
                    $salesChannelId
                )
            );
        }

        return $config;
    }

    public function checkConfiguration(string $domain, Context $context): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', SystemConfigDefinitionService::class));

        return $this->systemConfigDefinitionService->checkConfiguration($domain, $context);
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function enrichValues(array $config, ?string $salesChannelId): array
    {
        foreach ($config as &$card) {
            if (!\is_array($card['elements'] ?? false)) {
                continue;
            }

            foreach ($card['elements'] as &$element) {
                $element['value'] = $this->systemConfigService->get(
                    $element['name'],
                    $salesChannelId
                ) ?? $element['config']['defaultValue'] ?? '';
            }
        }

        return $config;
    }
}
