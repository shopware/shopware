<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media\Validator;

use Symfony\Component\HttpFoundation\File\UploadedFile;

trait MimeTypeValidationTrait
{
    protected function checkMimeType(UploadedFile $file, array $allowedMimeTypes): bool
    {
        foreach ($allowedMimeTypes as $fileEndings => $mime) {
            $fileEndings = explode('|', $fileEndings);

            if (!\in_array(mb_strtolower($file->getExtension()), $fileEndings, true)
                && !\in_array(mb_strtolower($file->getClientOriginalExtension()), $fileEndings, true)
            ) {
                continue;
            }

            if (\is_array($mime) && \in_array($file->getMimeType(), $mime, true)) {
                return true;
            }
        }

        return false;
    }
}
