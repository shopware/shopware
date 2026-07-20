<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('discovery')]
class StorefrontMediaValidatorRegistry
{
    /**
     * @internal
     *
     * @param iterable<StorefrontMediaValidatorInterface> $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    public function validate(UploadedFile $file, string $type): void
    {
        $filtered = [];
        foreach ($this->validators as $validator) {
            if (mb_strtolower($validator->getType()) === mb_strtolower($type)) {
                $filtered[] = $validator;
            }
        }

        if ($filtered === []) {
            throw StorefrontFrameworkException::mediaValidatorMissing($type);
        }

        foreach ($filtered as $validator) {
            $validator->validate($file);
        }
    }
}
