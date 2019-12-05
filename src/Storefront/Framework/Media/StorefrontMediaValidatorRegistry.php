<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media;

use Shopware\Storefront\Framework\Media\Exception\MediaValidatorMissingException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StorefrontMediaValidatorRegistry
{
    /**
     * @var StorefrontMediaValidatorInterface[]
     */
    private $validators;

    public function __construct(iterable $validators)
    {
        $this->validators = $validators;
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
