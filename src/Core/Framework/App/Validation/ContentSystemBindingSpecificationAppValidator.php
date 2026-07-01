<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemBindingSpecificationPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemBindingSpecificationAppValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly YamlBindingSpecificationLoader $loader,
    ) {
    }

    /**
     * Validates schema structure only (syntax, required fields, constraints), not name collisions.
     * Bindings are unique within their app, enforced by the DB unique key at install time.
     */
    public function validate(Manifest $manifest, Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $directory = $manifest->getPath() . '/' . ContentSystemBindingSpecificationPersister::DIRECTORY;
        $appName = $manifest->getMetadata()->getName();

        try {
            $this->loader->loadDtosFromDirectory($directory, 'app:' . $appName, $appName);
        } catch (ContentSystemException $e) {
            $errors->add(new ContentSystemBindingSpecificationSchemaError($directory, $e->getMessage()));
        }

        return $errors;
    }
}
