<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
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
        $errors = [];
        foreach ($this->validators as $validator) {
            $errors = [...$errors, ...$validator->validate($manifest, $context)];
        }

        if ($errors === []) {
            return;
        }

        throw AppException::validationFailed($manifest->getMetadata()->getName(), $errors);
    }

    public function throwOnFirstError(Manifest $manifest, Context $context): void
    {
        foreach ($this->validators as $validator) {
            $error = $validator->validate($manifest, $context)[0] ?? null;

            if ($error !== null) {
                throw AppException::validationFailedFromError($error);
            }
        }
    }
}
