<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidIdentifierException extends ShopwareHttpException
{
    public function __construct(string $fieldName)
    {
        parent::__construct('The identifier of {{ fieldName }} should not contain pipe character.', ['fieldName' => $fieldName]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_INVALID_IDENTIFIER';
    }
}
