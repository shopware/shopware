<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ManifestValidator
{
    /**
     * @param iterable<AbstractManifestValidator> $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    public function validate(Manifest $manifest, Context $context): void
    {
        $errors = new ErrorCollection();
        foreach ($this->validators as $validator) {
            $errors->addErrors($validator->validate($manifest, $context));
        }

        if ($errors->count() === 0) {
            return;
        }

        throw AppException::validationFailed($manifest->getMetadata()->getName(), $errors);
    }

    public function throwOnFirstError(Manifest $manifest, Context $context): void
    {
        foreach ($this->validators as $validator) {
            $error = $validator->validate($manifest, $context)->first();

            if ($error !== null) {
                throw AppException::validationFailedFromError($error);
            }
        }
    }
}
