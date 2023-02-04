<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('sales-channel')]
class DuplicateFileNameException extends ShopwareHttpException
{
    public function __construct(
        string $number,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'File name "{{ fileName }}" already exists.',
            ['fileName' => $number],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_FILE_NAME';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
