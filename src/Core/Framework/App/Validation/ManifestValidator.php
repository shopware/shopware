<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Result;

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

    /**
     * @return Result<list<Error>>
     */
    public function validate(Manifest $manifest, Context $context): Result
    {
        $errors = [];
        foreach ($this->validators as $validator) {
            $errors = [...$errors, ...$validator->validate($manifest, $context)];
        }

        return $errors === [] ? Result::ok() : Result::failed($errors);
    }
}
