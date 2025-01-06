<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media\Validator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Media\StorefrontMediaValidatorInterface;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('buyers-experience')]
class StorefrontMediaDocumentValidator implements StorefrontMediaValidatorInterface
{
    use MimeTypeValidationTrait;

    public function getType(): string
    {
        return 'documents';
    }

    public function validate(UploadedFile $file): void
    {
        $valid = $this->checkMimeType($file, [
            'pdf' => ['application/pdf', 'application/x-pdf'],
        ]);

        if (!$valid) {
            throw StorefrontFrameworkException::fileTypeNotAllowed((string) $file->getMimeType(), $this->getType());
        }
    }
}
