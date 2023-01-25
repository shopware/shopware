<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppNameValidator extends AbstractManifestValidator
{
    public function validate(Manifest $manifest, ?Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $appName = substr($manifest->getPath(), strrpos($manifest->getPath(), '/') + 1);

        if ($appName !== $manifest->getMetadata()->getName()) {
            $errors->add(new AppNameError($manifest->getMetadata()->getName()));
        }

        return $errors;
    }
}
