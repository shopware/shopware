<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LanguageForeignKeyDeleteException extends ShopwareHttpException
{
    /**
     * @deprecated tag:v6.5.0 - $language parameter will be removed
     */
    public function __construct(string $language, $e)
    {
        parent::__construct(
            'The language cannot be deleted because foreign key constraints exist.',
            [],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LANGUAGE_FOREIGN_KEY_DELETE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
