<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppNameValidator extends AbstractManifestValidator
{
    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $appName = strtolower(substr($manifest->getPath(), (int) strrpos($manifest->getPath(), '/') + 1));

        if ($appName === strtolower($manifest->getMetadata()->getName())) {
            return [];
        }

        return [new AppNameError($manifest->getMetadata()->getName())];
    }
}
