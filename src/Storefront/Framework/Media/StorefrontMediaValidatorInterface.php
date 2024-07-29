<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('buyers-experience')]
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
