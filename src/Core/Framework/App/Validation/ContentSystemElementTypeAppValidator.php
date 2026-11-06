<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemElementTypeSchemaError;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemElementTypeAppValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly YamlTypeLoader $loader,
    ) {
    }

    /**
     * Validates schema structure only (syntax, required fields, constraints), not name collisions.
     * Collision detection runs later in the persister when the app is actually installed.
     *
     * @return list<Error>
     */
    public function validate(Manifest $manifest, Context $context): array
    {
        $typesDir = $manifest->getPath() . '/Resources/content-system/types';
        $appName = $manifest->getMetadata()->getName();

        try {
            $this->loader->loadFromDirectory($typesDir, 'app:' . $appName, $appName);
        } catch (ContentSystemException $e) {
            return [new ContentSystemElementTypeSchemaError(
                $typesDir,
                $e->getMessage()
            )];
        }

        return [];
    }
}
