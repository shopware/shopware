<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemLayoutPresetSchemaError;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemLayoutPresetAppValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly YamlLayoutPresetLoader $loader,
    ) {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, Context $context): array
    {
        $presetsDir = $manifest->getPath() . '/Resources/content-system/presets';
        $appName = $manifest->getMetadata()->getName();

        try {
            $this->loader->loadDtosFromDirectory($presetsDir, 'app:' . $appName, $appName);
        } catch (ContentSystemException $e) {
            return [new ContentSystemLayoutPresetSchemaError(
                $presetsDir,
                $e->getMessage()
            )];
        }

        return [];
    }
}
