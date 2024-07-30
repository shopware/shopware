<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Media\Exception\MediaValidatorMissingException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('buyers-experience')]
class StorefrontMediaValidatorRegistry
{
    /**
     * @internal
     *
     * @param StorefrontMediaValidatorInterface[] $validators
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

        if (empty($filtered)) {
            throw new MediaValidatorMissingException($type);
        }

        foreach ($filtered as $validator) {
            $validator->validate($file);
        }
    }
}
