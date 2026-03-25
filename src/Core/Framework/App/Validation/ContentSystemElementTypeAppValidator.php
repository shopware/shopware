<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemElementTypeSchemaError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
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

    public function validate(Manifest $manifest, Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $typesDir = $manifest->getPath() . '/Resources/content-system/types';
        $appName = $manifest->getMetadata()->getName();

        try {
            $this->loader->loadFromDirectory($typesDir, 'app:' . $appName, $appName);
        } catch (ContentSystemException $e) {
            $errors->add(new ContentSystemElementTypeSchemaError(
                $typesDir,
                $e->getMessage()
            ));
        }

        return $errors;
    }
}
