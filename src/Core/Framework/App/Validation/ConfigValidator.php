<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ConfigurationError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ConfigValidator extends AbstractManifestValidator
{
    private const ALLOWED_APP_CONFIGURATION_COMPONENTS = [
        'sw-entity-single-select',
        'sw-entity-multi-id-select',
        'sw-media-field',
        'sw-text-editor',
        'sw-snippet-field',
    ];

    public function __construct(private readonly ConfigReader $configReader)
    {
    }

    public function validate(Manifest $manifest, ?Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();
        $config = $this->getConfiguration($manifest->getPath());

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

        if (!empty($invalids)) {
            $errors->add(new ConfigurationError($invalids));
        }

        return $errors;
    }

    private function getConfiguration(string $appFolder): array
    {
        $configPath = sprintf('%s/Resources/config/config.xml', $appFolder);

        if (!file_exists($configPath)) {
            return [];
        }

        return $this->configReader->read($configPath);
    }
}
