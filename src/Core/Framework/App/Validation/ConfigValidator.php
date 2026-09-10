<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Validation\Error\ConfigurationError;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ConfigValidator extends AbstractManifestValidator
{
    private const CONFIG_PATH = 'Resources/config/config.xml';

    private const ALLOWED_APP_CONFIGURATION_COMPONENTS = [
        'sw-entity-single-select',
        'sw-entity-multi-id-select',
        'sw-media-field',
        'sw-text-editor',
        'sw-snippet-field',
    ];

    public function __construct(
        private readonly ConfigReader $configReader,
        private readonly SourceResolver $sourceResolver
    ) {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $config = $this->getConfiguration($manifest);

        $invalids = [];
        foreach ($config as $card) {
            foreach ($card['elements'] as $element) {
                // Rendering of custom admin components via <component> element is not allowed for apps
                // as it may lead to code execution by apps in the administration
                if (\array_key_exists('componentName', $element)
                    && !\in_array($element['componentName'], self::ALLOWED_APP_CONFIGURATION_COMPONENTS, true)
                ) {
                    $invalids[] = $element['componentName'];
                }
            }
        }

        if ($invalids === []) {
            return [];
        }

        return [new ConfigurationError($invalids, $manifest->getMetadata()->getName())];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getConfiguration(Manifest $manifest): array
    {
        $fs = $this->sourceResolver->filesystemForManifest($manifest);

        if (!$fs->has(self::CONFIG_PATH)) {
            return [];
        }

        return $this->configReader->read($fs->path(self::CONFIG_PATH));
    }
}
