<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface StorefrontMediaValidatorInterface
{
    /**
     * Returns the supported file type
     */
    public function getType(): string;

    /**
     * Validates the provided file
     */
    public function validate(UploadedFile $file): void;
}
